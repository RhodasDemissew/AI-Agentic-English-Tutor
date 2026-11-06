from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import hashlib
base = Path('aienglishlesson')
target = Path('dist/mod_aienglishlesson-0.4.0.zip')
target.parent.mkdir(exist_ok=True)
with ZipFile(target, 'w', ZIP_DEFLATED) as z:
    for p in sorted(base.rglob('*')):
        if p.is_file():
            z.write(p, p.as_posix())
with ZipFile(target) as z:
    assert z.testzip() is None
    assert 'aienglishlesson/version.php' in z.namelist()
    assert all(n.startswith('aienglishlesson/') for n in z.namelist())
print(str(target), hashlib.sha256(target.read_bytes()).hexdigest())
