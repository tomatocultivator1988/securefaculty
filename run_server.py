import os
import sys
import subprocess
import shutil
import socket

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
PUBLIC_DIR = os.path.join(BASE_DIR, 'public')

# Find PHP executable across common Windows environments
php_exe = shutil.which('php')
if not php_exe:
    candidates = [
        r"C:\xampp\php\php.exe",
        r"C:\php\php.exe",
        os.path.expandvars(r"%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"),
        os.path.expandvars(r"%ProgramFiles%\PHP\php.exe"),
    ]
    for cand in candidates:
        if os.path.exists(cand):
            php_exe = cand
            break

if not php_exe:
    sys.exit(
        "Error: PHP executable not found.\n"
        "Please ensure PHP 8.0+ is installed:\n"
        "  - If using XAMPP, PHP is located at C:\\xampp\\php\\php.exe\n"
        "  - Or install via winget: 'winget install PHP.PHP.8.3'\n"
        "  - Or download from https://windows.php.net/download/ and add to PATH."
    )

def get_lan_ip():
    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        s.connect(("8.8.8.8", 80))
        ip = s.getsockname()[0]
        s.close()
        return ip
    except Exception:
        return "127.0.0.1"

lan_ip = get_lan_ip()
port = 8000

print("=" * 65)
print(" SECUREFACULTY: SYSTEMATIC RECORDS MANAGEMENT PORTAL")
print(" CICI Department - ISUFST Main Campus")
print("=" * 65)
print(f"[*] PHP Executable:    {php_exe}")
print(f"[*] Public Directory:  {PUBLIC_DIR}")
print(f"[*] Python Engine:     {sys.executable}")
print("-" * 65)
print("Demo Evaluation Accounts:")
print("  - Dean / Administrator:  dean    / admin123")
print("  - Faculty Member:        mikhail / faculty123")
print("  - Faculty Member:        raejan  / faculty123")
print("  - Faculty Member:        eljay   / faculty123")
print("-" * 65)
print(f"[*] Local Host URL:    http://localhost:{port}")
print(f"[*] Network (LAN) URL: http://{lan_ip}:{port}")
print("  --> Devices on the same Wi-Fi / Local Network can connect via:")
print(f"      http://{lan_ip}:{port}")
print("-" * 65)
print("Server is active. Press Ctrl+C to terminate.")
print("=" * 65)

ROUTER_FILE = os.path.join(BASE_DIR, 'router.php')

# Launch PHP built-in web server binding to 0.0.0.0 (all network interfaces)
cmd = [php_exe, "-S", f"0.0.0.0:{port}", ROUTER_FILE]
try:
    subprocess.run(cmd)
except KeyboardInterrupt:
    print("\nSecureFaculty server stopped.")
