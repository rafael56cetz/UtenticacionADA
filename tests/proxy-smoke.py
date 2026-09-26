"""Live relay checks: no biometric media, passwords or authenticated sessions."""
import http.client
from pathlib import Path
import socket
import ssl
import sys

address = sys.argv[1] if len(sys.argv) > 1 else "192.168.1.79"
checks = 0
def check(value, label):
    global checks
    assert value, label
    checks += 1
    print("PASS", label)

def connect(authority, method="CONNECT"):
    sock = socket.create_connection((address, 8899), timeout=5)
    sock.sendall(f"{method} {authority} HTTP/1.1\r\nHost: {authority}\r\n\r\n".encode("ascii"))
    response = bytearray()
    while b"\r\n\r\n" not in response:
        data = sock.recv(1024)
        if not data:
            raise AssertionError("No relay response")
        response.extend(data)
    return sock, int(response.split()[1])

for authority, method, expected in [("example.com:443", "CONNECT", 403), ("127.0.0.1:3306", "CONNECT", 403), ("acceso.ada.test:8000", "CONNECT", 403), ("acceso.ada.test:8443", "GET", 405)]:
    sock, status = connect(authority, method)
    sock.close()
    check(status == expected, "Relay rejects " + method + " " + authority)

sock, status = connect("acceso.ada.test:8443")
check(status == 200, "Relay accepts only the app authority")
root = Path(__file__).resolve().parent.parent
context = ssl.create_default_context(cafile=str(root / "storage/private/tls/ca/rootCA.pem"))
with context.wrap_socket(sock, server_hostname="acceso.ada.test") as secured:
    secured.sendall(b"GET /api/session HTTP/1.1\r\nHost: acceso.ada.test:8443\r\nConnection: close\r\n\r\n")
    response = http.client.HTTPResponse(secured)
    response.begin()
    check(response.status == 200, "TLS certificate and hostname verified through relay without DNS lookup")
    check("; secure" in response.getheader("Set-Cookie", "").lower(), "HTTPS session cookie remains Secure")
print(f"{checks} relay checks passed; phone browser confirmation pending.")
