from pathlib import Path
for sourcepath in Path('aienglishlesson/amd/src').glob('*.js'):
    source = sourcepath.read_text(encoding='utf-8')
    target = Path('aienglishlesson/amd/build') / (sourcepath.stem + '.min.js')
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(source.replace('define(', "define('mod_aienglishlesson/" + sourcepath.stem + "', ", 1), encoding='utf-8')
