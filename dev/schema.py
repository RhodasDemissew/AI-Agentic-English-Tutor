from pathlib import Path
import xml.etree.ElementTree as E
root=E.Element('XMLDB',PATH='mod/aienglishlesson/db',VERSION='20261104',COMMENT='AI English Lessons tables')
tables=E.SubElement(root,'TABLES')
def table(name, fields, indexes=[]):
 t=E.SubElement(tables,'TABLE',NAME=name,COMMENT=name); fs=E.SubElement(t,'FIELDS')
 E.SubElement(fs,'FIELD',NAME='id',TYPE='int',LENGTH='10',NOTNULL='true',SEQUENCE='true')
 for name,typ,default in fields:
  a=dict(NAME=name,TYPE=typ,NOTNULL='true',SEQUENCE='false')
  if typ=='int': a['LENGTH']='10'
  if typ=='char': a['LENGTH']='255'
  if default is not None and not (typ == 'char' and default == ''): a['DEFAULT']=str(default)
  E.SubElement(fs,'FIELD',**a)
 ks=E.SubElement(t,'KEYS'); E.SubElement(ks,'KEY',NAME='primary',TYPE='primary',FIELDS='id')
 if indexes:
  ix=E.SubElement(t,'INDEXES')
  for name,fields,unique in indexes: E.SubElement(ix,'INDEX',NAME=name,FIELDS=fields,UNIQUE=unique)
table('aienglishlesson',[(x,'int',0) for x in ['course']]+[('name','char',''),('intro','text',None),('introformat','int',0),('gradeband','char','adult'),('proficiency','char','unspecified'),('topic','char',''),('pagerange','char',''),('duration','int',30),('instructions','text',None),('timecreated','int',0),('timemodified','int',0)], [('course','course','false')])
table('aiel_versions',[('lessonid','int',0),('status','char','draft'),('content','text',None),('audiostatus','char','none'),('timecreated','int',0),('timemodified','int',0)], [('lessonstatus','lessonid, status','false')])
table('aiel_jobs',[('lessonid','int',0),('status','char','queued'),('attempts','int',0),('diagnostic','char',''),('timecreated','int',0),('timemodified','int',0)], [('lessonstatus','lessonid, status','false')])
table('aiel_progress',[('lessonid','int',0),('versionid','int',0),('userid','int',0),('sectionno','int',0),('timemodified','int',0)], [('usersection','versionid, userid, sectionno','true'),('lessonuser','lessonid, userid','false')])
table('aiel_usage',[('timecreated','int',0)], [('timecreated','timecreated','false')])
table('aiel_responses',[('lessonid','int',0),('versionid','int',0),('userid','int',0),('sectionno','int',0),('response','text',None),('timemodified','int',0)], [('usersection','versionid, userid, sectionno','true'),('lessonuser','lessonid, userid','false')])
Path('aienglishlesson/db/install.xml').write_bytes(E.tostring(root,encoding='utf-8',xml_declaration=True))
