#!/usr/bin/env python3
"""Keep local secrets in backend/.env and reuse them across Compose services."""
from pathlib import Path
import argparse,base64,os,secrets
parser=argparse.ArgumentParser()
parser.add_argument('--local',action='store_true',help='Select Mailpit and local mock payment services')
parser.add_argument('--demo',action='store_true',help='Create a private password for the complete local demo')
parser.add_argument('--no-root-env',action='store_true',help='Use Compose --env-file backend/.env without a root symlink')
parser.add_argument('--port',type=int)
parser.add_argument('--mailpit-port',type=int)
args=parser.parse_args()
root=Path(__file__).resolve().parents[1]
backend=root/'backend'/'.env'
compose_env=root/'.env'
def parse(text):
    return dict(line.split('=',1) for line in text.splitlines() if '=' in line and not line.lstrip().startswith('#'))
try: legacy=parse(compose_env.read_text(encoding="utf-8-sig"))
except OSError: legacy={}
if not backend.exists():backend.write_text((backend.parent/'.env.example').read_text(encoding='utf-8-sig'),encoding='utf-8')
lines=backend.read_text(encoding="utf-8-sig").splitlines()
values=parse('\n'.join(lines))
def set_value(name,value,overwrite=False):
    if not overwrite and values.get(name):return
    value=str(value)
    for index,line in enumerate(lines):
        if line.startswith(name+'='):lines[index]=name+'='+value;break
    else:lines.append(name+'='+value)
    values[name]=value
for name,value in parse((backend.parent/'.env.example').read_text(encoding='utf-8-sig')).items():
    if name not in values:set_value(name,value)
for name,value in [('DB_PASSWORD',secrets.token_hex(24)),('DB_ROOT_PASSWORD',secrets.token_hex(32)),('APP_KEY','base64:'+base64.b64encode(secrets.token_bytes(32)).decode())]:
    set_value(name,legacy.get(name) or value)
for name,value in [('APP_PORT',legacy.get('APP_PORT','8000')),('MAILPIT_PORT','8025'),('LOCAL_UID',getattr(os,'getuid',lambda:1000)()),('LOCAL_GID',getattr(os,'getgid',lambda:1000)())]:set_value(name,value)
for name,value in [('APP_PORT',args.port),('MAILPIT_PORT',args.mailpit_port)]:
    if value is not None:
        if not 1024<=value<=65535:parser.error('Ports must be between 1024 and 65535')
        set_value(name,value,True)
if args.local:
    for name,value in [('APP_ENV','local'),('APP_URL','http://localhost:'+values['APP_PORT']),('MAIL_MAILER','smtp'),('MAIL_HOST','mailpit'),('MAIL_PORT','1025'),('MAIL_SCHEME','smtp'),('QUEUE_CONNECTION','database'),('PAYMENT_MODE','mock'),('VNPAY_ENABLED','true'),('MOMO_ENABLED','true'),('BANK_TRANSFER_ENABLED','true'),('BANK_TRANSFER_MODE','mock')]:set_value(name,value,True)
if values.get('APP_ENV')=='local':
    set_value('AIRLINE_BOOTSTRAP_ADMIN','true')
    set_value('LOCAL_ADMIN_EMAIL','admin@airline.test')
    set_value('LOCAL_ADMIN_PASSWORD',secrets.token_urlsafe(24))
if args.demo:
    if values.get('APP_ENV')!='local':parser.error('Demo setup requires APP_ENV=local')
    set_value('LOCAL_DEMO_PASSWORD',secrets.token_urlsafe(24))
if values.get('PAYMENT_MODE')=='mock':
    for name in ['VNPAY_HASH_SECRET','MOMO_ACCESS_KEY','MOMO_SECRET_KEY']:set_value(name,secrets.token_hex(32))
    set_value('VNPAY_TMN_CODE','LOCAL'+secrets.token_hex(3).upper())
    set_value('MOMO_PARTNER_CODE','LOCAL'+secrets.token_hex(4).upper())
if values.get('BANK_TRANSFER_MODE')=='mock':
    set_value('BANK_NAME','"Ngân hàng thử nghiệm"');set_value('BANK_ACCOUNT_NUMBER','000000000000');set_value('BANK_ACCOUNT_NAME','"AIRLINE LOCAL"')
temporary=backend.with_name('.env.tmp')
temporary.write_text('\n'.join(lines)+'\n',encoding='utf-8');temporary.chmod(0o600);temporary.replace(backend)
if not args.no_root_env and not compose_env.is_symlink():
    if compose_env.exists():compose_env.unlink()
    if os.name=='nt':compose_env.write_text(backend.read_text(encoding="utf-8-sig"),encoding="utf-8")
    else:compose_env.symlink_to('backend/.env')
print('Configuration ready in backend/.env; existing credentials preserved; secret values not displayed.')
