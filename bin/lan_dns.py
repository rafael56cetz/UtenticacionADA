"""Small authoritative UDP DNS for acceso.ada.test on the demonstration LAN.
No forwarding, recursion, query logging or external connection. Stop with Ctrl+C.
"""
import argparse
import ipaddress
import json
from pathlib import Path
import socket
import struct
import time

NAME = "acceso.ada.test"

def answer(packet, address):
    if len(packet) < 12 or len(packet) > 512:
        return None
    ident, flags, questions, answers, authority, additional = struct.unpack("!6H", packet[:12])
    if flags & 0xF800 or questions != 1 or answers or authority:
        return None
    offset, labels = 12, []
    while offset < len(packet):
        length = packet[offset]
        offset += 1
        if length == 0:
            break
        if length > 63 or offset + length > len(packet):
            return None
        try:
            labels.append(packet[offset:offset + length].decode("ascii").lower())
        except UnicodeError:
            return None
        offset += length
        if offset > 267:
            return None
    else:
        return None
    if offset + 4 > len(packet):
        return None
    kind, qclass = struct.unpack("!HH", packet[offset:offset + 4])
    question = packet[12:offset + 4]
    matches = ".".join(labels) == NAME and qclass == 1
    records = 1 if matches and kind == 1 else 0
    response_flags = 0x8400 | (flags & 0x0100) | (0 if matches else 3)
    reply = struct.pack("!6H", ident, response_flags, 1, records, 0, 0) + question
    if records:
        reply += b"\xc0\x0c" + struct.pack("!HHIH", 1, 1, 60, 4) + ipaddress.IPv4Address(address).packed
    return reply

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--bind", required=True, type=ipaddress.IPv4Address)
    parser.add_argument("--subnet", required=True, type=ipaddress.IPv4Network)
    parser.add_argument("--status-file", type=Path, help="Optional private diagnostic counters; never records queried names.")
    args = parser.parse_args()
    if not args.bind.is_private or args.bind.is_loopback or args.bind not in args.subnet or args.subnet.prefixlen < 16:
        parser.error("Choose the laptop private LAN address and a /16 or narrower local subnet.")
    with socket.socket(socket.AF_INET, socket.SOCK_DGRAM) as server:
        server.bind((str(args.bind), 53))
        status = {"startedAt": time.time(), "received": 0, "answered": 0, "siteQueries": 0}
        def save_status():
            if args.status_file:
                try:
                    temporary = args.status_file.with_suffix(".tmp")
                    temporary.write_text(json.dumps(status), encoding="utf-8")
                    temporary.replace(args.status_file)
                except OSError:
                    # A locked diagnostic file must never interrupt DNS service.
                    pass
        save_status()
        print(f"{NAME} -> {args.bind}; UDP 53; clients {args.subnet}; Ctrl+C to stop.", flush=True)
        while True:
            packet, peer = server.recvfrom(513)
            if ipaddress.ip_address(peer[0]) not in args.subnet:
                continue
            status["received"] += 1
            response = answer(packet, args.bind)
            if response:
                server.sendto(response, peer)
                status["answered"] += 1
                if response[3] & 15 == 0:
                    status["siteQueries"] += 1
                    status["lastSiteClient"] = peer[0]
                    status["lastSiteQueryAt"] = time.time()
            save_status()

if __name__ == "__main__":
    main()
