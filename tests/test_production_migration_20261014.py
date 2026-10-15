"""Integration checks using an explicitly supplied disposable database server.

Example: python3 test_production_migration_20261014.py --socket=/path/to/test.sock
Never point this test at production. It creates and drops only uniquely named
cea_migration_test_* databases. The application connection/config is not read.
"""
import argparse
import re
import subprocess
import uuid
from pathlib import Path

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--socket', required=True, help='Socket of a disposable test server')
parser.add_argument('--client', default='/Applications/XAMPP/xamppfiles/bin/mysql')
args = parser.parse_args()
root = Path(__file__).resolve().parents[2]
sql = (root / 'migration_production_complete_20261014.sql').read_text()
dump = (root / 'u182589698_jntuaceasarb_server_131026.sql').read_text()
legacy = (root / 'jntuacea/migrations/2026_10_09_exam_results_scheme_sync_and_see_attainment.sql').read_text()
prefix = 'cea_migration_test_' + uuid.uuid4().hex[:10]
databases = []
passes = []
reference = None

def query(text, database=None, expect_error=None):
    command = [args.client, '--no-defaults', '--socket=' + args.socket, '-u', 'root', '-N', '-B']
    if database:
        command.append(database)
    result = subprocess.run(command, input=text, text=True, capture_output=True)
    if expect_error:
        assert result.returncode != 0 and expect_error in result.stderr, result.stderr
    else:
        assert result.returncode == 0, result.stderr
    return result.stdout.strip()

def new_database(suffix, restore=True):
    name = prefix + '_' + suffix
    query(f'CREATE DATABASE `{name}` CHARACTER SET utf8mb4;')
    databases.append(name)
    if restore:
        # This supplied schema export includes regulation/settings seed rows but
        # omits referenced operational data (e.g. programs). Only the fixture
        # restore disables FK checking; the migration runs in a fresh connection
        # with FK checks enabled. No source dump is changed.
        query('SET FOREIGN_KEY_CHECKS=0;\n' + dump + '\nSET FOREIGN_KEY_CHECKS=1;', name)
    return name

fixtures = """
SET FOREIGN_KEY_CHECKS=0;
INSERT INTO curriculum_subjects (id,prog_id,reg_id,spec_id,yearsem,subject_sno,subcode,sub_fullname,sub_shortname,sub_type)
VALUES (10001,1,4,1,'I Yr - I Sem',1,'TEST101','Migration Test','Test','Theory');
INSERT INTO subjects (id,subject_sno,subcode,sub_shortname,sub_fullname,sub_type,class_id,curr_sub_id)
VALUES (10001,'1','TEST101','Test','Migration Test','Theory',10001,10001),
       (10002,'1','TEST101','Test','Migration Test','Theory',10002,10001);
INSERT INTO course_outcomes (id,sub_id,co_number,co_description,curr_sub_id)
VALUES (10001,10001,1,'Preserve original outcome',NULL);
INSERT INTO po_pso (id,acad_year,code) VALUES (10001,'2026-27','PO1');
INSERT INTO co_po_mapping (co_id,po_id,weightage) VALUES (10001,10001,3);
INSERT INTO users (id,username,password,name,role,status)
VALUES (10001,'migration_prof','test','Professor Test','faculty',1),
       (10002,'migration_guest','test','Guest Test','faculty',1);
INSERT INTO faculties (id,username,designation,facultyid,dept_id,status)
VALUES (10001,'migration_prof','Professor',10001,1,'1'),
       (10002,'migration_guest','Guest Faculty',10002,1,'1');
SET FOREIGN_KEY_CHECKS=1;
"""

def signature(database):
    return query("""SELECT reg_id, regulation_code, category, setting_key, setting_value FROM academic_settings WHERE setting_key NOT IN ('bloom_hots_lots_enforce','daac_audit_variance_threshold_pct','diary_lag_alert_threshold') ORDER BY id;
SELECT id, regulation, prog_id, total_degree_credits FROM regulations ORDER BY id;
SELECT id, reg_id, category_code, target_credits FROM regulation_course_categories ORDER BY id;
SELECT id, reg_id, type_code, cie_max_marks, see_max_marks FROM regulation_course_types ORDER BY id;""", database)

def check_schema(database):
    expected = set(re.findall(r'CREATE TABLE IF NOT EXISTS `([^`]+)`', sql))
    actual = set(query('SHOW TABLES;', database).splitlines())
    assert expected <= actual, expected - actual
    local_schema = (root/'u182589698_jntuaceasarb_local_scheme.sql').read_text()
    expected_columns = {(table, column) for table, body in re.findall(r'CREATE TABLE `([^`]+)` \((.*?)\) ENGINE=',local_schema,re.S)
                        for column in re.findall(r'^  `([^`]+)` ',body,re.M)}
    actual_columns = {tuple(line.split('\t')) for line in query('SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE();',database).splitlines()}
    assert expected_columns <= actual_columns, expected_columns - actual_columns
    assert query("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='external_assessment_marks' AND COLUMN_NAME='evidence_basis';",database) == '60'
    assert 'MALPRACTICE' in query("SHOW COLUMNS FROM exam_results LIKE 'result_status';", database)
    for table, name, columns in [('exam_results','idx_er_student_subject','student_id,subject_id'),
                                 ('exam_results','idx_er_htno_subcode','htno,subject_code'),
                                 ('course_outcomes','idx_co_curr_num','curr_sub_id,co_number'),
                                 ('faculties','idx_faculties_dept_sort','dept_id,sort_order')]:
        assert query(f"SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{table}' AND INDEX_NAME='{name}';",database) == columns
    assert query('SELECT COUNT(*) FROM schema_migrations;',database) == '6'
    if reference:
        def columns(name):
            rows = query("SELECT TABLE_NAME,COLUMN_NAME,DATA_TYPE,COLUMN_TYPE,IS_NULLABLE,COALESCE(COLUMN_DEFAULT,'<NULL>'),COALESCE(CHARACTER_MAXIMUM_LENGTH,0),COALESCE(NUMERIC_PRECISION,0),COALESCE(NUMERIC_SCALE,0),COALESCE(NULLIF(EXTRA,''),'<EMPTY>') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE();", name)
            return {tuple(row[:2]): row[2:] for line in rows.splitlines() for row in [line.split('\t')]}
        expected_definitions, actual_definitions = columns(reference), columns(database)
        for key, expected_definition in expected_definitions.items():
            actual_definition = actual_definitions[key]
            assert expected_definition[0] == actual_definition[0], (key,expected_definition,actual_definition)
            assert expected_definition[2:4] == actual_definition[2:4], (key,expected_definition,actual_definition)
            assert expected_definition[7].lower() == actual_definition[7].lower(), (key,expected_definition,actual_definition)
            if expected_definition[0] == 'decimal':
                ep, es = map(int,expected_definition[5:7])
                ap, ass = map(int,actual_definition[5:7])
                assert ass >= es and ap-ass >= ep-es, (key,expected_definition,actual_definition)
            elif expected_definition[0] in ('varchar','char','text','longtext','mediumtext'):
                assert int(actual_definition[4]) >= int(expected_definition[4]), (key,expected_definition,actual_definition)
            elif expected_definition[0] == 'enum':
                assert all(value in actual_definition[1] for value in re.findall(r"'[^']*'",expected_definition[1])), (key,expected_definition,actual_definition)
        def indexes(name):
            rows=query("SELECT TABLE_NAME,INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(CONCAT(COLUMN_NAME,':',COALESCE(SUB_PART,0)) ORDER BY SEQ_IN_INDEX) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() GROUP BY TABLE_NAME,INDEX_NAME,NON_UNIQUE;",name)
            return {tuple(row[:2]):tuple(row[2:]) for line in rows.splitlines() for row in [line.split('\t')]}
        expected_indexes, actual_indexes = indexes(reference), indexes(database)
        expected_indexes[('course_outcomes','idx_co_curr_num')] = ('1','curr_sub_id:0,co_number:0')
        for key, definition in expected_indexes.items():
            actual_index = actual_indexes.get(key)
            assert actual_index and int(actual_index[0]) <= int(definition[0]) and actual_index[1] == definition[1], (key,definition,actual_index)
        def foreign_keys(name):
            return set(query("SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL;",name).splitlines())
        assert foreign_keys(reference) <= foreign_keys(database)

try:
    assert not re.search(r'^\s*--',sql,re.M)
    assert not re.search(r'(ADD COLUMN|ADD (UNIQUE )?INDEX|CREATE INDEX) IF NOT EXISTS',sql,re.I)
    reference = new_database('reference',False)
    query('SET FOREIGN_KEY_CHECKS=0;\n'+(root/'u182589698_jntuaceasarb_local_scheme.sql').read_text(),reference)
    fresh = new_database('fresh')
    query(fixtures,fresh)
    before = signature(fresh)
    query(sql,fresh)
    check_schema(fresh)
    assert signature(fresh) == before
    assert query('SELECT COUNT(*) FROM course_outcomes;',fresh) == '3'
    assert query('SELECT COUNT(*) FROM co_po_mapping;',fresh) == '3'
    assert query('SELECT sort_order FROM faculties ORDER BY id;',fresh) == '1\n2'
    assert query("SELECT is_enabled_globally FROM system_feature_modules WHERE module_key='MOD_RESULTS_PUBLISH';",fresh) == '0'
    passes.append('Fresh baseline: all tables/indexes, CO backfill, faculty order and settings preservation')

    query("UPDATE faculties SET sort_order=99 WHERE id=10001; UPDATE system_feature_modules SET is_enabled_globally=0, faculty_visibility='HIDDEN' WHERE module_key='MOD_LAB_RUBRICS'; UPDATE academic_settings SET setting_value='23' WHERE setting_key='diary_lag_alert_threshold' AND reg_id=4; UPDATE lab_rubric_criteria SET max_marks=4 WHERE criterion_name='Viva-Voce';",fresh)
    query("SET SESSION sql_mode='ANSI_QUOTES,NO_BACKSLASH_ESCAPES,STRICT_ALL_TABLES,ONLY_FULL_GROUP_BY';\n"+sql,fresh)
    check_schema(fresh)
    assert query('SELECT sort_order FROM faculties WHERE id=10001;',fresh) == '99'
    assert query("SELECT is_enabled_globally,faculty_visibility FROM system_feature_modules WHERE module_key='MOD_LAB_RUBRICS';",fresh) == '0\tHIDDEN'
    assert query("SELECT setting_value FROM academic_settings WHERE setting_key='diary_lag_alert_threshold' AND reg_id=4;",fresh) == '23'
    assert query("SELECT max_marks FROM lab_rubric_criteria WHERE criterion_name='Viva-Voce';",fresh) == '4.00'
    assert query('SELECT COUNT(*) FROM course_outcomes;',fresh) == '3'
    assert query('SELECT COUNT(*) FROM lab_rubric_templates;',fresh) == '1'
    assert query('SELECT COUNT(*) FROM lab_rubric_criteria;',fresh) == '3'
    passes.append('Replay under strict/ANSI modes retains custom faculty, modules, settings and rubric values')

    partial = new_database('partial')
    query(fixtures,partial)
    for path in ['database/baselines/20261007_accreditation.sql',
                 'database/migrations/20261008_001_trusted_core.sql',
                 'database/migrations/20261008_002_document_lifecycle.sql']:
        query((root/path).read_text(),partial)
    query("ALTER TABLE faculties ADD COLUMN sort_order int NOT NULL DEFAULT 0; UPDATE faculties SET sort_order=42 WHERE id=10001; CREATE INDEX idx_er_student_subject ON exam_results(student_id); UPDATE lab_rubric_templates SET template_name='Custom college rubric',total_max_marks=20 WHERE id=1;",partial)
    query(sql,partial)
    check_schema(partial)
    assert query('SELECT sort_order FROM faculties ORDER BY id;',partial) == '42\n43'
    assert query("SELECT total_max_marks FROM lab_rubric_templates WHERE id=1;",partial) == '20.00'
    assert query("SELECT COUNT(*) FROM lab_rubric_criteria c JOIN lab_rubric_templates t ON t.id=c.template_id WHERE t.template_name='Standard Autonomous Practical Rubric';",partial) == '3'
    passes.append('Partial older imports recover; incorrect index repaired and custom rubric IDs retained')

    interrupted = new_database('interrupted')
    query(sql.split('/* 3. Result status')[0],interrupted)
    query(sql,interrupted)
    check_schema(interrupted)
    passes.append('Interrupted consolidated import resumes successfully')

    standalone = new_database('standalone')
    query(legacy,standalone)
    query(legacy,standalone)
    assert query("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='external_assessment_marks' AND COLUMN_NAME='evidence_basis';",standalone) == '60'
    passes.append('Repaired October 9 migration imports twice on the raw server baseline')

    empty = new_database('empty',False)
    query(sql,empty,'cea_error_required_table_academic_settings_missing')
    assert query('SHOW TABLES;',empty) == ''
    passes.append('Wrong/empty schema fails before application changes')

    query("UPDATE schema_migrations SET checksum=REPEAT('0',64) WHERE version='20261008_001_trusted_core.sql';",fresh)
    query(sql,fresh,'cea_error_migration_checksum_conflict')
    assert query("SELECT checksum FROM schema_migrations WHERE version='20261008_001_trusted_core.sql';",fresh) == '0'*64
    passes.append('Checksum conflict fails early and existing ledger evidence is retained')

    duplicates = new_database('duplicates')
    query((root/'jntuacea/migrations/2026_10_07_accreditation_and_governance.sql').read_text(),duplicates)
    query("SET FOREIGN_KEY_CHECKS=0; INSERT INTO lab_rubric_evaluations (uglab_mark_id,criterion_name,marks_obtained) VALUES (10001,'Duplicate test',2),(10001,'Duplicate test',3);",duplicates)
    query(sql,duplicates,'Duplicate entry')
    assert query('SELECT COUNT(*) FROM lab_rubric_evaluations;',duplicates) == '2'
    passes.append('Duplicate academic evidence is reported without deleting either row')

    widened = new_database('widened')
    query("ALTER TABLE assessment_questions MODIFY COLUMN marks decimal(20,3) NOT NULL; SET FOREIGN_KEY_CHECKS=0; INSERT INTO assessment_questions (id,component_id,question_label,question_type,marks,blooms_level_id) VALUES (10001,10001,'Q1','SUBJECTIVE',123456789012345.123,1);", widened)
    query(sql,widened)
    assert query("SELECT NUMERIC_PRECISION,NUMERIC_SCALE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='assessment_questions' AND COLUMN_NAME='marks';",widened) == '22\t5'
    assert query('SELECT marks FROM assessment_questions WHERE id=10001;',widened) == '123456789012345.12300'
    passes.append('Decimal widening preserves existing integer capacity and every stored mark')

    master_duplicates = new_database('master_duplicates')
    query(fixtures,master_duplicates)
    query("INSERT INTO course_outcomes (sub_id,curr_sub_id,co_number,co_description) VALUES (NULL,10001,1,'First master'),(NULL,10001,1,'Second master');",master_duplicates)
    query(sql,master_duplicates,'cea_error_duplicate_master_course_outcomes')
    assert query('SELECT COUNT(*) FROM course_outcomes;',master_duplicates) == '3'
    assert query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ai_jobs';",master_duplicates) == '0'
    passes.append('Duplicate master COs fail before application changes and preserve both records')

    unique_lookup = new_database('unique_lookup')
    query('CREATE UNIQUE INDEX idx_er_student_subject ON exam_results(student_id,subject_id);',unique_lookup)
    query(sql,unique_lookup)
    assert query("SELECT MIN(NON_UNIQUE) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='exam_results' AND INDEX_NAME='idx_er_student_subject';",unique_lookup) == '0'
    passes.append('Existing stronger unique lookup index is preserved')

    unique_conflict = new_database('unique_conflict')
    query('CREATE UNIQUE INDEX idx_er_student_subject ON exam_results(htno);',unique_conflict)
    query(sql,unique_conflict,'cea_error_unique_index_conflict')
    assert query("SELECT COLUMN_NAME,NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='exam_results' AND INDEX_NAME='idx_er_student_subject';",unique_conflict) == 'htno\t0'
    passes.append('Conflicting custom unique constraint is reported without dropping it')

    capacity = new_database('capacity')
    query('ALTER TABLE assessment_questions MODIFY COLUMN marks decimal(65,0) NOT NULL;',capacity)
    query(sql,capacity,'cea_error_decimal_capacity_limit')
    assert query("SELECT NUMERIC_PRECISION,NUMERIC_SCALE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='assessment_questions' AND COLUMN_NAME='marks';",capacity) == '65\t0'
    passes.append('Decimal engine capacity limit fails without narrowing the original column')

    statuses = new_database('statuses')
    query("ALTER TABLE exam_results MODIFY COLUMN result_status enum('PASS','FAIL','ABSENT','WITHHELD','RECHECK') NOT NULL DEFAULT 'PASS'; SET FOREIGN_KEY_CHECKS=0; INSERT INTO exam_results (notification_id,htno,subject_code,result_status) VALUES (10001,'TEST10001','TEST101','RECHECK');",statuses)
    query(legacy,statuses)
    query(sql,statuses)
    assert query('SELECT result_status FROM exam_results;',statuses) == 'RECHECK'
    assert 'MALPRACTICE' in query("SHOW COLUMNS FROM exam_results LIKE 'result_status';",statuses)
    passes.append('Adding malpractice preserves additional existing result statuses and rows')

    full_older = new_database('full_older')
    query((root/'database/migrations/archive/migration_production_server_sync_20261008.sql').read_text(),full_older)
    query(legacy,full_older)
    query((root/'database/migrations/20261013_001_faculty_sort_order.sql').read_text(),full_older)
    query(sql,full_older)
    check_schema(full_older)
    passes.append('Fully applied old consolidated route upgrades without checksum conflicts')

    malformed = new_database('malformed')
    query(sql.split('/* 3. Result status')[0],malformed)
    query('ALTER TABLE ai_providers DROP COLUMN credential_env;',malformed)
    missing = query(sql,malformed,'cea_error_incomplete_schema_review_missing_columns')
    assert 'ai_providers\tcredential_env' in missing and 'COMPLETE:' not in missing
    assert query('SELECT COUNT(*) FROM schema_migrations;',malformed) == '0'
    passes.append('Incomplete pre-existing schema cannot record successful migration versions')

    flattened = new_database('flattened')
    query(sql.replace('\n',' '),flattened)
    check_schema(flattened)
    passes.append('Import remains valid when comments and statements share physical lines')

    passes.append('Complete reference comparison: 90 tables, 821 column definitions, indexes and foreign-key mappings')
finally:
    for database in databases:
        query(f'DROP DATABASE `{database}`;')

for item in passes:
    print('PASS:',item)
print(f'{len(passes)} integration scenarios passed; disposable databases removed.')
