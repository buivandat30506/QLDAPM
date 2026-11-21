#!/usr/bin/env python3
"""Portable complete demo setup. Requires Python 3 and Docker Compose v2 only."""
import argparse
import os
from pathlib import Path
import subprocess
import sys
import urllib.request
import time

ROOT=Path(__file__).resolve().parents[1]

def run(args):
    print('+ '+' '.join(map(str,args)),flush=True)
    subprocess.run(list(map(str,args)),cwd=ROOT,check=True)

def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--project',default='airline',help='Compose project; use a different project for a separate database')
    parser.add_argument('--port',type=int)
    parser.add_argument('--mailpit-port',type=int)
    parser.add_argument('--minimal',action='store_true',help='Install code/schema/admin only, without demo business data')
    parser.add_argument('--skip-build',action='store_true',help='Reuse the existing airline-php:dev image')
    args=parser.parse_args()
    configure=[sys.executable,'scripts/configure.py','--local']
    if not args.minimal:configure+=['--demo']
    for flag,value in [('--port',args.port),('--mailpit-port',args.mailpit_port)]:
        if value is not None:configure += [flag,str(value)]
    run(configure)
    values=dict(line.split('=',1) for line in (ROOT/'backend/.env').read_text(encoding='utf-8-sig').splitlines() if '=' in line and not line.startswith('#'))
    compose=['docker','compose','--env-file','backend/.env','--project-name',args.project]
    bundle=os.environ.get('AIRLINE_CA_BUNDLE') or os.environ.get('CODEX_PROXY_CERT') or values.get('AIRLINE_CA_BUNDLE')
    if bundle:
        if not Path(bundle).is_file():raise RuntimeError('The configured custom CA bundle does not exist')
        os.environ['AIRLINE_CA_BUNDLE']=str(Path(bundle).resolve())
        compose += ['-f','compose.yaml','-f','compose.trust.yaml']
    run(['docker','compose','version'])
    if not args.skip_build:run(compose+['build','app'])
    run(compose+['run','--rm','--no-deps','-e','COMPOSER_HOME=/tmp/composer','app','composer','install','--prefer-dist','--no-interaction'])
    node=['docker','run','--rm','--user',values['LOCAL_UID']+':'+values['LOCAL_GID'],'--volume',str(ROOT)+':/workspace','--workdir','/workspace','node:22-bookworm-slim']
    run(node+['npm','ci','--no-audit','--no-fund'])
    run(node+['npm','run','assets'])
    run(compose+['up','-d','--wait','db1','db2','db3','mailpit'])
    artisan=compose+['run','--rm','app','php','artisan']
    run(artisan+['migrate','--force'])
    run(artisan+['airline:bootstrap-local'])
    if not args.minimal:run(artisan+['airline:demo-install'])
    run(compose+['up','-d','app','worker','mail-worker'])
    url='http://localhost:'+values['APP_PORT']
    for attempt in range(30):
        try:
            with urllib.request.urlopen(url+'/health/ready',timeout=5) as response:
                if response.status==200:break
        except (OSError,urllib.error.URLError):
            if attempt==29:raise RuntimeError('Application did not become ready; inspect docker compose logs app')
            time.sleep(2)
    print('\nReady: '+url+'\nMailpit: http://localhost:'+values['MAILPIT_PORT'])
    print('Login: '+('admin@airline.test' if args.minimal else 'demo.admin@airline.test'))
    print('Password: '+('LOCAL_ADMIN_PASSWORD' if args.minimal else 'LOCAL_DEMO_PASSWORD')+' in backend/.env (private to this machine).')

if __name__=='__main__':
    try:main()
    except (subprocess.CalledProcessError,RuntimeError,OSError) as error:
        print('Setup stopped: '+str(error)+'\nExisting databases and files have not been deleted.',file=sys.stderr)
        sys.exit(1)
