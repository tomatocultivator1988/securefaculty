import os
import sys
import subprocess
import shutil

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
PUBLIC_DIR = os.path.join(BASE_DIR, 'public')

# Find PHP executable
php_exe = shutil.which('php')
if not php_exe:
    # Check default winget location
    winget_php = r"C:\Users\User\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
    if os.path.exists(winget_php):
        php_exe = winget_php
    else:
        sys.exit("Error: PHP executable not found. Please install PHP or add it to PATH.")

print("=" * 65)
print(" SECUREFACULTY: SYSTEMATIC RECORDS MANAGEMENT PORTAL")
print(" CICI Department - ISUFST Main Campus")
print("=" * 65)
print(f"[*] PHP Executable: {php_exe}")
print(f"[*] Public Directory: {PUBLIC_DIR}")
print(f"[*] Python Bridge: {sys.executable}")
print("-" * 65)
print("Demo Evaluation Accounts:")
print("  - Dean / Administrator:  dean    / admin123")
print("  - Faculty Member:        mikhail / faculty123")
print("  - Faculty Member:        raejan  / faculty123")
print("  - Faculty Member:        eljay   / faculty123")
print("-" * 65)
print("Server URL: http://localhost:8000")
print("Press Ctrl+C to terminate the local server.")
print("=" * 65)

# Launch PHP built-in web server
cmd = [php_exe, "-S", "127.0.0.1:8000", "-t", PUBLIC_DIR]
try:
    subprocess.run(cmd)
except KeyboardInterrupt:
    print("\nSecureFaculty server stopped.")
