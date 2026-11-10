import pathlib
import subprocess
import sys
import tempfile
import unittest
import shutil

ROOT=pathlib.Path(__file__).resolve().parents[2]

class DemoConfigurationTest(unittest.TestCase):
    def setUp(self):
        self.temp=tempfile.TemporaryDirectory()
        self.root=pathlib.Path(self.temp.name)
        (self.root/'scripts').mkdir();(self.root/'backend').mkdir()
        shutil.copyfile(ROOT/'scripts/configure.py',self.root/'scripts/configure.py')
        shutil.copyfile(ROOT/'backend/.env.example',self.root/'backend/.env.example')
    def tearDown(self):self.temp.cleanup()
    def configure(self,*args):
        return subprocess.run([sys.executable,str(self.root/'scripts/configure.py'),*args],capture_output=True,text=True)
    def values(self):
        return dict(line.split('=',1) for line in (self.root/'backend/.env').read_text(encoding='utf-8').splitlines() if '=' in line and not line.startswith('#'))
    def test_fresh_demo_is_complete_unicode_safe_and_has_private_credentials(self):
        result=self.configure('--local','--demo','--no-root-env','--port','18080','--mailpit-port','18025')
        self.assertEqual(0,result.returncode,result.stderr)
        values=self.values()
        for key in ['DB_PASSWORD','DB_ROOT_PASSWORD','APP_KEY','LOCAL_ADMIN_PASSWORD','LOCAL_DEMO_PASSWORD']:self.assertGreaterEqual(len(values[key]),16)
        self.assertEqual('http://localhost:18080',values['APP_URL']);self.assertEqual('18025',values['MAILPIT_PORT'])
        self.assertIn('thử nghiệm',values['BANK_NAME']);self.assertEqual('airline',values['DB_DATABASE'])
        self.assertFalse((self.root/'.env').exists())
        self.assertNotIn(values['LOCAL_DEMO_PASSWORD'],result.stdout+result.stderr)
    def test_reconfiguration_preserves_keys_passwords_and_user_settings(self):
        self.assertEqual(0,self.configure('--local','--demo','--no-root-env').returncode)
        before=self.values()
        with (self.root/'backend/.env').open('a',encoding='utf-8')as file:file.write('USER_NOTE="Dữ liệu riêng"\n')
        self.assertEqual(0,self.configure('--local','--demo','--no-root-env').returncode)
        after=self.values()
        for key in ['DB_PASSWORD','DB_ROOT_PASSWORD','APP_KEY','LOCAL_ADMIN_PASSWORD','LOCAL_DEMO_PASSWORD']:self.assertEqual(before[key],after[key])
        self.assertEqual('"Dữ liệu riêng"',after['USER_NOTE'])
    def test_demo_does_not_accept_production_environment(self):
        (self.root/'backend/.env').write_text('APP_ENV=production\n',encoding='utf-8')
        self.assertNotEqual(0,self.configure('--demo','--no-root-env').returncode)
        self.assertEqual('APP_ENV=production\n',(self.root/'backend/.env').read_text(encoding='utf-8'))

if __name__=='__main__':unittest.main()
