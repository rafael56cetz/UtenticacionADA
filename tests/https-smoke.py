"""Read-only HTTPS checks against this local installation; no login or media."""
import http.cookiejar
import json
from pathlib import Path
import ssl
import urllib.error
import urllib.request

root = Path(__file__).resolve().parent.parent
context = ssl.create_default_context(cafile=str(root / "storage/private/tls/ca/rootCA.pem"))
cookies = http.cookiejar.CookieJar()
client = urllib.request.build_opener(urllib.request.ProxyHandler({}), urllib.request.HTTPSHandler(context=context), urllib.request.HTTPCookieProcessor(cookies))
origin = "https://acceso.ada.test:8443"
checks = 0
def check(condition, label):
    global checks
    assert condition, label
    checks += 1
    print("PASS", label)

with client.open(origin + "/api/session", timeout=10) as response:
    data = json.load(response)
    check(response.status == 200 and data["ok"] and data["data"]["authenticated"] is False, "Certificate/hostname verified and anonymous HTTPS session")
    cookie = response.headers.get("Set-Cookie", "").lower()
    check("; secure" in cookie and "; httponly" in cookie and "samesite=lax" in cookie, "Secure, HttpOnly and SameSite cookie")
with client.open(origin + "/api/capabilities", timeout=10) as response:
    check(all(json.load(response)["data"].values()), "Four methods enabled through HTTPS")
for path, expected in [("/api/users", 401), ("/.env", 404), ("/phpmyadmin/", 403), ("/adminer/", 403)]:
    try:
        client.open(origin + path, timeout=10)
        raise AssertionError("Unexpected access: " + path)
    except urllib.error.HTTPError as error:
        check(error.code == expected, path + " rejected")
with client.open("http://localhost:8088/", timeout=10) as response:
    check(response.url == origin + "/", "Previous localhost URL redirects to HTTPS")
print(f"{checks} HTTPS checks passed. Android trust, DNS and sensor remain physical tests.")
