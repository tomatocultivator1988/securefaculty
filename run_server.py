import os
import sys
import subprocess
import shutil
import socket
import time

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

def is_port_open(host="127.0.0.1", port=3306):
    s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    s.settimeout(0.5)
    try:
        s.connect((host, port))
        s.close()
        return True
    except Exception:
        return False

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

# Automated Database Backend Management
env = os.environ.copy()
mysql_process = None
xampp_mysqld = r"C:\xampp\mysql\bin\mysqld.exe"
xampp_myini = r"C:\xampp\mysql\bin\my.ini"
db_status_text = ""

if is_port_open("127.0.0.1", 3306):
    db_status_text = "MySQL Production (Active on port 3306)"
    env["DB_DRIVER"] = "mysql"
elif os.path.exists(xampp_mysqld):
    db_status_text = "Auto-starting XAMPP MySQL..."
    try:
        creation_flags = 0x08000000 if sys.platform == 'win32' else 0  # CREATE_NO_WINDOW
        cmd_mysql = [xampp_mysqld, f"--defaults-file={xampp_myini}", "--standalone"]
        mysql_process = subprocess.Popen(cmd_mysql, creationflags=creation_flags)
        
        # Wait up to 4 seconds for MySQL to be ready
        started = False
        for _ in range(8):
            time.sleep(0.5)
            if is_port_open("127.0.0.1", 3306):
                started = True
                break
        
        if started:
            db_status_text = "MySQL Production (Auto-started from XAMPP on port 3306)"
            env["DB_DRIVER"] = "mysql"
        else:
            db_status_text = "Zero-config SQLite (Fallback: MySQL did not respond)"
            env["DB_DRIVER"] = "sqlite"
    except Exception as e:
        db_status_text = f"Zero-config SQLite (Fallback: {e})"
        env["DB_DRIVER"] = "sqlite"
else:
    db_status_text = "Zero-config SQLite (storage/securefaculty.sqlite)"
    env["DB_DRIVER"] = "sqlite"

print("=" * 65)
print(" SECUREFACULTY: SYSTEMATIC RECORDS MANAGEMENT PORTAL")
print(" CICI Department - ISUFST Main Campus")
print("=" * 65)
print(f"[*] PHP Executable:    {php_exe}")
print(f"[*] Public Directory:  {PUBLIC_DIR}")
print(f"[*] Python Engine:     {sys.executable}")
print(f"[*] Database Backend:  {db_status_text}")
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
    subprocess.run(cmd, env=env)
except KeyboardInterrupt:
    print("\nSecureFaculty server stopped.")
finally:
    if mysql_process and mysql_process.poll() is None:
        print("[*] Stopping background MySQL...")
        mysql_process.terminate()
        try:
            mysql_process.wait(timeout=3)
        except Exception:
            mysql_process.kill()
        print("[*] Background MySQL stopped.")
