import importlib.util
from pathlib import Path
import struct
spec = importlib.util.spec_from_file_location("lan_dns", Path(__file__).resolve().parent.parent / "bin/lan_dns.py")
dns = importlib.util.module_from_spec(spec)
spec.loader.exec_module(dns)
def query(name="acceso.ada.test", kind=1):
    return struct.pack("!6H", 123, 256, 1, 0, 0, 0) + b"".join(bytes([len(p)]) + p.encode() for p in name.split(".")) + b"\0" + struct.pack("!HH", kind, 1)
response = dns.answer(query(), "192.168.43.10")
assert response[-4:] == bytes([192, 168, 43, 10]) and struct.unpack("!6H", response[:12])[3] == 1
assert dns.answer(query("ACCESO.ADA.TEST"), "192.168.43.10")[-4:] == response[-4:]
assert struct.unpack("!6H", dns.answer(query(kind=28), "192.168.43.10")[:12])[3] == 0
assert dns.answer(query("other.test"), "192.168.43.10")[3] & 15 == 3
assert dns.answer(b"short", "192.168.43.10") is None
assert dns.answer(query()[:12] + b"\xc0\x0c\0\1\0\1", "192.168.43.10") is None
assert dns.answer(query() + b"x" * 512, "192.168.43.10") is None
print("7 DNS packet checks passed; actual hotspot resolution is not covered.")
