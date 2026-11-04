from pathlib import Path
import tarfile
import shutil
import subprocess
root = Path('.local/moodle')
temporary = Path('dev/moodle-source.new.tar')
if not (root / 'config.php').exists():
    shutil.copyfile('dev/config.php', root / 'config.php')
subprocess.run(['git', '-c', 'safe.directory=' + root.resolve().as_posix(), '-C', str(root),
                'archive', '--format=tar', '--output=' + str(temporary.resolve()),
                'HEAD'], check=True)
with tarfile.open(temporary, 'a') as archive:
    archive.add(root / 'config.php', arcname='config.php')
with tarfile.open(temporary) as archive:
    names = archive.getnames()
    assert 'version.php' in names and 'config.php' in names and 'user/view.php' in names
temporary.replace('dev/moodle-source.tar')
print('Prepared official Moodle source archive for Docker volume.')
