"""Local CONNECT relay for this one HTTPS site; never decrypts TLS or resolves public names."""
import argparse
import ipaddress
import json
from pathlib import Path
import select
import socket
import socketserver
import threading
import time

AUTHORITY = "acceso.ada.test:8443"

class SiteProxy(socketserver.ThreadingTCPServer):
    daemon_threads = True
    allow_reuse_address = False
    request_queue_size = 12

    def __init__(self, address, subnet, status_file):
        self.subnet = subnet
        self.target = (address[0], 8443)
        self.slots = threading.BoundedSemaphore(12)
        self.status_file = status_file
        self.status_lock = threading.Lock()
        self.status = {"startedAt": time.time(), "accepted": 0, "denied": 0}
        super().__init__(address, Connection)

    def verify_request(self, request, client_address):
        return ipaddress.ip_address(client_address[0]) in self.subnet

    def process_request(self, request, client_address):
        if not self.slots.acquire(blocking=False):
            self.shutdown_request(request)
            return
        try:
            super().process_request(request, client_address)
        except Exception:
            self.slots.release()
            raise

    def process_request_thread(self, request, client_address):
        try:
            super().process_request_thread(request, client_address)
        finally:
            self.slots.release()

    def record(self, outcome, client):
        with self.status_lock:
            self.status[outcome] += 1
            if outcome == "accepted":
                self.status["lastAcceptedClient"] = client
                self.status["lastAcceptedAt"] = time.time()
            if self.status_file:
                try:
                    temporary = self.status_file.with_suffix(".tmp")
                    temporary.write_text(json.dumps(self.status), encoding="utf-8")
                    temporary.replace(self.status_file)
                except OSError:
                    pass

class Connection(socketserver.BaseRequestHandler):
    def error(self, code):
        self.server.record("denied", self.client_address[0])
        self.request.sendall(f"HTTP/1.1 {code} Rejected\r\nContent-Length: 0\r\nConnection: close\r\n\r\n".encode("ascii"))

    def handle(self):
        try:
            self.request.settimeout(5)
            data = bytearray()
            while b"\r\n\r\n" not in data:
                chunk = self.request.recv(1024)
                if not chunk:
                    return
                data.extend(chunk)
                if len(data) > 8192:
                    self.error(431)
                    return
            header, remaining = bytes(data).split(b"\r\n\r\n", 1)
            parts = header.split(b"\r\n", 1)[0].split()
            if len(parts) != 3 or parts[2] not in (b"HTTP/1.0", b"HTTP/1.1"):
                self.error(400)
                return
            if parts[0] != b"CONNECT":
                self.error(405)
                return
            if parts[1].lower() != AUTHORITY.encode("ascii"):
                self.error(403)
                return
            # Target is fixed by startup, never derived from client input.
            with socket.create_connection(self.server.target, timeout=5) as target:
                target.settimeout(10)
                self.request.settimeout(10)
                self.request.sendall(b"HTTP/1.1 200 Connection Established\r\n\r\n")
                self.server.record("accepted", self.client_address[0])
                if remaining:
                    target.sendall(remaining)
                deadline = time.monotonic() + 300
                while time.monotonic() < deadline:
                    readable, _, _ = select.select([self.request, target], [], [], 30)
                    if not readable:
                        return
                    for source in readable:
                        chunk = source.recv(65536)
                        if not chunk:
                            return
                        (target if source is self.request else self.request).sendall(chunk)
        except OSError:
            return

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--bind", type=ipaddress.IPv4Address, required=True)
    parser.add_argument("--subnet", type=ipaddress.IPv4Network, required=True)
    parser.add_argument("--status-file", type=Path)
    args = parser.parse_args()
    private_ranges = (ipaddress.ip_network("10.0.0.0/8"), ipaddress.ip_network("172.16.0.0/12"), ipaddress.ip_network("192.168.0.0/16"))
    if not any(args.bind in network for network in private_ranges) or args.bind not in args.subnet or args.subnet.prefixlen < 16:
        parser.error("Use the private laptop LAN address and a /16 or narrower local subnet.")
    with SiteProxy((str(args.bind), 8899), args.subnet, args.status_file) as server:
        print(f"LAN relay {args.bind}:8899 -> {AUTHORITY}; TLS remains end-to-end.", flush=True)
        server.serve_forever()

if __name__ == "__main__":
    main()
