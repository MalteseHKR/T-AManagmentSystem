import subprocess
import os

# OpenVPN configuration file path
openvpn_config_path = "/home/cdadmin/Desktop/FaceRecognition/open.ovpn"

def start_vpn(username, password):
    credentials_path = "/home/cdadmin/Desktop/FaceRecognition/credentials.txt"
    with open(credentials_path, "w") as f:
        f.write(f"{username}\n{password}\n")
    vpn_process = subprocess.Popen(['sudo', 'openvpn', '--config', openvpn_config_path, '--auth-user-pass', credentials_path])
    return vpn_process, credentials_path

def stop_vpn(vpn_process, credentials_path):
    vpn_process.terminate()
    os.remove(credentials_path)