from pathlib import Path
source = Path('aienglishlesson/amd/src/recorder.js').read_text(encoding='utf-8')
target = Path('aienglishlesson/amd/build/recorder.min.js')
target.parent.mkdir(parents=True, exist_ok=True)
target.write_text(source.replace("define(['core/str']", "define('mod_aienglishlesson/recorder', ['core/str']", 1), encoding='utf-8')
