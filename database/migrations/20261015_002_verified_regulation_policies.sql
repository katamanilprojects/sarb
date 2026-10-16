-- MySQL 8.0 production data migration. Requires 20261015_001 first.
-- Before running directly in a MySQL client, set an actual operator identity and authority:
-- SET @cea_regulation_actor_id = <active admin/academic_section/superadmin users.id>;
-- SET @cea_regulation_reason = 'Reviewed regulation PDFs and user-approved equivalence/rounding';
-- With tools/migrate.php use --actor and --reason together with its verified-backup arguments.
-- Formulas and grade tables were reviewed against the four supplied regulation PDFs.
-- User decisions: nearest-whole PG CIA; B.Tech R20/R26 equal R23; use MCA fractional grade points.
-- This migration does not modify entered marks, results, publications or SEE evidence.
-- Special skill/audit/integrated/project/co-curricular types and attainment overrides are excluded.
-- Routine DDL is outside the data transaction. All persistent data updates and snapshots are atomic.

DROP TEMPORARY TABLE IF EXISTS cea_verified_regulation_catalog;
CREATE TEMPORARY TABLE cea_verified_regulation_catalog (
  program_norm VARCHAR(32) NOT NULL,
  program_level VARCHAR(8) NOT NULL,
  regulation_code VARCHAR(10) NOT NULL,
  settings_json JSON NOT NULL,
  source_name VARCHAR(100) NOT NULL,
  source_sha256 CHAR(64) NOT NULL,
  source_section VARCHAR(255) NOT NULL,
  PRIMARY KEY(program_norm, regulation_code)
);
INSERT INTO cea_verified_regulation_catalog
(program_norm,program_level,regulation_code,settings_json,source_name,source_sha256,source_section) VALUES
('MCA','PG','R20','{"theory_continuous_eval_marks":0,"lab_cia_record_marks":0,"theory_cia_total_marks":40,"theory_mid_better_weight":0.7,"theory_mid_lesser_weight":0.3,"cia_scheme_json":{"theory":{"kind":"PG_THEORY","required_assessments":[1,2,3],"better_weight":0.7,"lesser_weight":0.3,"component_maxima":{"1":30,"2":30,"3":10},"rounding":"NEAREST_INTEGER"},"pe":{"kind":"PG_THEORY","required_assessments":[1,2,3],"better_weight":0.7,"lesser_weight":0.3,"component_maxima":{"1":30,"2":30,"3":10},"rounding":"NEAREST_INTEGER"},"oe":{"kind":"PG_THEORY","required_assessments":[1,2,3],"better_weight":0.7,"lesser_weight":0.3,"component_maxima":{"1":30,"2":30,"3":10},"rounding":"NEAREST_INTEGER"},"lab":{"kind":"PG_LAB","required_assessments":[11],"component_maxima":{"11":40},"rounding":"NEAREST_INTEGER"}},"grade_bands_json":[{"grade":"S","points":10,"min_percent":95,"max_percent":100},{"grade":"A1","points":9.5,"min_percent":90,"max_percent":94},{"grade":"A2","points":9,"min_percent":85,"max_percent":89},{"grade":"A3","points":8.5,"min_percent":80,"max_percent":84},{"grade":"B1","points":8,"min_percent":75,"max_percent":79},{"grade":"B2","points":7.5,"min_percent":70,"max_percent":74},{"grade":"C1","points":7,"min_percent":65,"max_percent":69},{"grade":"C2","points":6.5,"min_percent":60,"max_percent":64},{"grade":"D1","points":6,"min_percent":55,"max_percent":59},{"grade":"D2","points":5.5,"min_percent":50,"max_percent":54},{"grade":"F","points":0,"min_percent":0,"max_percent":49}]}','mca_r20_regulations.pdf','d9266dc1397a603979f73697e427ad68556af9c71da9e55d5b7ea462c187d967','4(i–vii), 5'),
('MTECH','PG','R21','{"theory_continuous_eval_marks":0,"lab_cia_day_to_day_marks":10,"lab_cia_internal_test_marks":20,"lab_cia_record_marks":10,"theory_cia_total_marks":40,"theory_mid_better_weight":0.7,"theory_mid_lesser_weight":0.3,"cia_scheme_json":{"theory":{"kind":"PG_THEORY","required_assessments":[1,2,3],"better_weight":0.7,"lesser_weight":0.3,"component_maxima":{"1":30,"2":30,"3":10},"rounding":"NEAREST_INTEGER"},"pe":{"kind":"PG_THEORY","required_assessments":[1,2,3],"better_weight":0.7,"lesser_weight":0.3,"component_maxima":{"1":30,"2":30,"3":10},"rounding":"NEAREST_INTEGER"},"oe":{"kind":"PG_THEORY","required_assessments":[1,2,3],"better_weight":0.7,"lesser_weight":0.3,"component_maxima":{"1":30,"2":30,"3":10},"rounding":"NEAREST_INTEGER"},"lab":{"kind":"PG_LAB","required_assessments":[11,12],"component_maxima":{"11":30,"12":10},"rounding":"NEAREST_INTEGER"}},"grade_bands_json":[{"grade":"S","points":10,"min_percent":90,"max_percent":100},{"grade":"A","points":9,"min_percent":80,"max_percent":89},{"grade":"B","points":8,"min_percent":70,"max_percent":79},{"grade":"C","points":7,"min_percent":60,"max_percent":69},{"grade":"D","points":6,"min_percent":50,"max_percent":59},{"grade":"F","points":0,"min_percent":0,"max_percent":49}]}','mtech_r21_regulations.pdf','8b6e55c5d528b590e38e864c899ded2d17c4886d921dfdf8528a1593690b9556','5.1–5.4, 6'),
('MTECH','PG','R25','{"theory_continuous_eval_marks":10,"lab_cia_day_to_day_marks":10,"lab_cia_internal_test_marks":20,"lab_cia_record_marks":10,"theory_cia_total_marks":40,"theory_mid_better_weight":0.8,"theory_mid_lesser_weight":0.2,"cia_scheme_json":{"theory":{"kind":"PG_THEORY","required_assessments":[1,2,4],"better_weight":0.8,"lesser_weight":0.2,"component_maxima":{"1":30,"2":30,"4":10},"rounding":"NEAREST_INTEGER"},"pe":{"kind":"PG_THEORY","required_assessments":[1,2,4],"better_weight":0.8,"lesser_weight":0.2,"component_maxima":{"1":30,"2":30,"4":10},"rounding":"NEAREST_INTEGER"},"oe":{"kind":"PG_THEORY","required_assessments":[1,2,4],"better_weight":0.8,"lesser_weight":0.2,"component_maxima":{"1":30,"2":30,"4":10},"rounding":"NEAREST_INTEGER"},"lab":{"kind":"PG_LAB","required_assessments":[11,12],"component_maxima":{"11":30,"12":10},"rounding":"NEAREST_INTEGER"}},"grade_bands_json":[{"grade":"S","points":10,"min_percent":90,"max_percent":100},{"grade":"A","points":9,"min_percent":80,"max_percent":89},{"grade":"B","points":8,"min_percent":70,"max_percent":79},{"grade":"C","points":7,"min_percent":60,"max_percent":69},{"grade":"D","points":6,"min_percent":50,"max_percent":59},{"grade":"F","points":0,"min_percent":0,"max_percent":49}]}','mtech_r25_regulations.pdf','35f1e84b23ece5fb8338247bb3c181ea13174e3c74234829bbec5d0ac9e3ab17','8.1–8.4, 15'),
('BTECH','UG','R20','{"theory_mid_subjective_condensed":15,"theory_mid_objective_marks":10,"theory_assignment_marks":5,"lab_cia_day_to_day_marks":15,"lab_cia_internal_test_marks":15,"theory_cia_total_marks":30,"theory_mid_better_weight":0.8,"theory_mid_lesser_weight":0.2,"cia_scheme_json":{"theory":{"kind":"UG_THEORY","required_assessments":[1,2],"better_weight":0.8,"lesser_weight":0.2,"component_maxima":{"1":30,"2":30},"rounding":"NEAREST_INTEGER"},"pe":{"kind":"UG_THEORY","required_assessments":[1,2],"better_weight":0.8,"lesser_weight":0.2,"component_maxima":{"1":30,"2":30},"rounding":"NEAREST_INTEGER"},"oe":{"kind":"UG_THEORY","required_assessments":[1,2],"better_weight":0.8,"lesser_weight":0.2,"component_maxima":{"1":30,"2":30},"rounding":"NEAREST_INTEGER"},"lab":{"kind":"UG_LAB","required_assessments":[1],"component_maxima":{"1":30},"rounding":"NEAREST_INTEGER"}},"grade_bands_json":[{"grade":"A+","points":10,"min_percent":90,"max_percent":100},{"grade":"A","points":9,"min_percent":80,"max_percent":89},{"grade":"B","points":8,"min_percent":70,"max_percent":79},{"grade":"C","points":7,"min_percent":60,"max_percent":69},{"grade":"D","points":6,"min_percent":50,"max_percent":59},{"grade":"E","points":5,"min_percent":40,"max_percent":49},{"grade":"F","points":0,"min_percent":0,"max_percent":39}]}','btech_r23_regulations.pdf','ff69c5b814088cc6ddafadc0ab850f5442f49d9b2d0f18dca88a11e0ca09aa4a','9, 19; R20/R26 equivalence explicitly approved by user'),
('BTECH','UG','R23','{"theory_mid_subjective_condensed":15,"theory_mid_objective_marks":10,"theory_assignment_marks":5,"lab_cia_day_to_day_marks":15,"lab_cia_internal_test_marks":15,"theory_cia_total_marks":30,"theory_mid_better_weight":0.8,"theory_mid_lesser_weight":0.2,"cia_scheme_json":{"theory":{"kind":"UG_THEORY","required_assessments":[1,2],"better_weight":0.8,"lesser_weight":0.2,"component_maxima":{"1":30,"2":30},"rounding":"NEAREST_INTEGER"},"pe":{"kind":"UG_THEORY","required_assessments":[1,2],"better_weight":0.8,"lesser_weight":0.2,"component_maxima":{"1":30,"2":30},"rounding":"NEAREST_INTEGER"},"oe":{"kind":"UG_THEORY","required_assessments":[1,2],"better_weight":0.8,"lesser_weight":0.2,"component_maxima":{"1":30,"2":30},"rounding":"NEAREST_INTEGER"},"lab":{"kind":"UG_LAB","required_assessments":[1],"component_maxima":{"1":30},"rounding":"NEAREST_INTEGER"}},"grade_bands_json":[{"grade":"A+","points":10,"min_percent":90,"max_percent":100},{"grade":"A","points":9,"min_percent":80,"max_percent":89},{"grade":"B","points":8,"min_percent":70,"max_percent":79},{"grade":"C","points":7,"min_percent":60,"max_percent":69},{"grade":"D","points":6,"min_percent":50,"max_percent":59},{"grade":"E","points":5,"min_percent":40,"max_percent":49},{"grade":"F","points":0,"min_percent":0,"max_percent":39}]}','btech_r23_regulations.pdf','ff69c5b814088cc6ddafadc0ab850f5442f49d9b2d0f18dca88a11e0ca09aa4a','9, 19; R20/R26 equivalence explicitly approved by user'),
('BTECH','UG','R26','{"theory_mid_subjective_condensed":15,"theory_mid_objective_marks":10,"theory_assignment_marks":5,"lab_cia_day_to_day_marks":15,"lab_cia_internal_test_marks":15,"theory_cia_total_marks":30,"theory_mid_better_weight":0.8,"theory_mid_lesser_weight":0.2,"cia_scheme_json":{"theory":{"kind":"UG_THEORY","required_assessments":[1,2],"better_weight":0.8,"lesser_weight":0.2,"component_maxima":{"1":30,"2":30},"rounding":"NEAREST_INTEGER"},"pe":{"kind":"UG_THEORY","required_assessments":[1,2],"better_weight":0.8,"lesser_weight":0.2,"component_maxima":{"1":30,"2":30},"rounding":"NEAREST_INTEGER"},"oe":{"kind":"UG_THEORY","required_assessments":[1,2],"better_weight":0.8,"lesser_weight":0.2,"component_maxima":{"1":30,"2":30},"rounding":"NEAREST_INTEGER"},"lab":{"kind":"UG_LAB","required_assessments":[1],"component_maxima":{"1":30},"rounding":"NEAREST_INTEGER"}},"grade_bands_json":[{"grade":"A+","points":10,"min_percent":90,"max_percent":100},{"grade":"A","points":9,"min_percent":80,"max_percent":89},{"grade":"B","points":8,"min_percent":70,"max_percent":79},{"grade":"C","points":7,"min_percent":60,"max_percent":69},{"grade":"D","points":6,"min_percent":50,"max_percent":59},{"grade":"E","points":5,"min_percent":40,"max_percent":49},{"grade":"F","points":0,"min_percent":0,"max_percent":39}]}','btech_r23_regulations.pdf','ff69c5b814088cc6ddafadc0ab850f5442f49d9b2d0f18dca88a11e0ca09aa4a','9, 19; R20/R26 equivalence explicitly approved by user');

DROP PROCEDURE IF EXISTS cea_apply_verified_regulations_20261015;
DELIMITER $$
CREATE PROCEDURE cea_apply_verified_regulations_20261015()
SQL SECURITY INVOKER
BEGIN
  DECLARE v_actor INT;
  DECLARE v_reason TEXT;
  DECLARE v_reg INT;
  DECLARE v_locked INT;
  DECLARE v_done INT DEFAULT 0;
  DECLARE v_reg_count INT DEFAULT 0;
  DECLARE v_repair_count INT DEFAULT 0;
  DECLARE regulation_locks CURSOR FOR SELECT reg_id FROM cea_verified_regulation_targets ORDER BY reg_id;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done=1;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    DROP TEMPORARY TABLE IF EXISTS cea_verified_regulation_before;
    DROP TEMPORARY TABLE IF EXISTS cea_verified_regulation_changes;
    DROP TEMPORARY TABLE IF EXISTS cea_verified_regulation_values;
    DROP TEMPORARY TABLE IF EXISTS cea_verified_regulation_targets;
    RESIGNAL;
  END;

  SET v_actor=NULL;
  SET v_reason=NULLIF(TRIM(@cea_regulation_reason),'');
  SELECT id INTO v_actor FROM users
    WHERE id=@cea_regulation_actor_id AND status=1
      AND role IN('admin','superadmin','academic_section');
  IF v_actor IS NULL OR v_reason IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Set an active academic administrator @cea_regulation_actor_id and @cea_regulation_reason.';
  END IF;
  IF NOT EXISTS(SELECT 1 FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='academic_settings'
        AND INDEX_NAME='uk_regulation_scope_setting') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Apply the regulation-setting-scope migration first.';
  END IF;
  SET v_done=0;

  DROP TEMPORARY TABLE IF EXISTS cea_verified_regulation_targets;
  CREATE TEMPORARY TABLE cea_verified_regulation_targets AS
    SELECT r.id reg_id,r.prog_id,c.*
    FROM cea_verified_regulation_catalog c
    JOIN programs p ON BINARY REPLACE(REPLACE(REPLACE(UPPER(p.prog_shortname),'.',''),' ',''),'-','')=BINARY c.program_norm
      AND BINARY p.program_level=BINARY c.program_level
    JOIN regulations r ON r.prog_id=p.id AND BINARY UPPER(TRIM(r.regulation))=BINARY c.regulation_code;
  IF (SELECT COUNT(*) FROM cea_verified_regulation_targets)<>6 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Require all six expected program/regulation records before applying.';
  END IF;
  IF EXISTS(SELECT 1 FROM cea_verified_regulation_targets GROUP BY program_norm,regulation_code HAVING COUNT(*)<>1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Require exactly one MCA R20, MTech R21/R25 and BTech R20/R23/R26 regulation. Review program mappings.';
  END IF;
  IF EXISTS(SELECT 1 FROM cea_verified_regulation_targets t
      WHERE NOT EXISTS(SELECT 1 FROM specialization sp WHERE sp.prog_id=t.prog_id)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Every target program requires a specialization/department for audit scope.';
  END IF;

  START TRANSACTION;
  OPEN regulation_locks;
  lock_loop: LOOP
    FETCH regulation_locks INTO v_reg;
    IF v_done=1 THEN LEAVE lock_loop; END IF;
    SELECT id INTO v_locked FROM regulations WHERE id=v_reg FOR UPDATE;
  END LOOP;
  CLOSE regulation_locks;

  IF EXISTS(SELECT 1 FROM curriculum_subjects cs JOIN cea_verified_regulation_targets t ON t.reg_id=cs.reg_id
      WHERE t.program_level='PG' AND LOWER(TRIM(cs.sub_type)) IN('theory','pe','oe','lab')
        AND (cs.cie_max_marks IS NULL OR cs.see_max_marks IS NULL OR
          NOT ((cs.cie_max_marks=30 AND cs.see_max_marks=70) OR (cs.cie_max_marks=40 AND cs.see_max_marks=60)))) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Review nonstandard PG curriculum maxima before applying; expected legacy 30/70 or verified 40/60.';
  END IF;

  DROP TEMPORARY TABLE IF EXISTS cea_verified_regulation_values;
  CREATE TEMPORARY TABLE cea_verified_regulation_values AS
    SELECT t.reg_id,t.regulation_code,k.setting_key,
      JSON_UNQUOTE(JSON_EXTRACT(t.settings_json,CONCAT('$."',k.setting_key,'"'))) setting_value,
      IF(RIGHT(k.setting_key,5)='_json','JSON','FLOAT') data_type,
      IF(k.setting_key='grade_bands_json','GRADING','CIA') category,
      IF(k.setting_key='cia_scheme_json',0,1) is_editable,
      CONCAT('Verified: ',t.source_name,' ',t.source_section) description
    FROM cea_verified_regulation_targets t
    JOIN JSON_TABLE(JSON_KEYS(t.settings_json),'$[*]' COLUMNS(setting_key VARCHAR(64) PATH '$')) k;

  DROP TEMPORARY TABLE IF EXISTS cea_verified_regulation_changes;
  CREATE TEMPORARY TABLE cea_verified_regulation_changes AS
    SELECT DISTINCT t.reg_id FROM cea_verified_regulation_targets t
    WHERE EXISTS(SELECT 1 FROM cea_verified_regulation_values v LEFT JOIN academic_settings a
        ON a.reg_id=v.reg_id AND BINARY a.setting_key=BINARY v.setting_key
      WHERE v.reg_id=t.reg_id AND (a.id IS NULL OR BINARY a.setting_value<>BINARY v.setting_value
        OR BINARY a.data_type<>BINARY v.data_type OR BINARY a.category<>BINARY v.category OR a.is_editable<>v.is_editable))
      OR EXISTS(SELECT 1 FROM curriculum_subjects cs WHERE cs.reg_id=t.reg_id AND t.program_level='PG'
        AND LOWER(TRIM(cs.sub_type)) IN('theory','pe','oe','lab') AND cs.cie_max_marks=30 AND cs.see_max_marks=70);
  SELECT COUNT(*) INTO v_reg_count FROM cea_verified_regulation_changes;

  DROP TEMPORARY TABLE IF EXISTS cea_verified_regulation_before;
  CREATE TEMPORARY TABLE cea_verified_regulation_before AS
    SELECT t.reg_id,JSON_OBJECT(
      'settings',COALESCE((SELECT JSON_ARRAYAGG(JSON_OBJECT('id',a.id,'setting_key',a.setting_key,
        'setting_value',a.setting_value,'data_type',a.data_type,'category',a.category,'is_editable',a.is_editable))
        FROM academic_settings a WHERE a.reg_id=t.reg_id),JSON_ARRAY()),
      'curriculum',COALESCE((SELECT JSON_ARRAYAGG(JSON_OBJECT('id',cs.id,'spec_id',cs.spec_id,'sub_type',cs.sub_type,
        'cie_max_marks',cs.cie_max_marks,'see_max_marks',cs.see_max_marks)) FROM curriculum_subjects cs
        WHERE cs.reg_id=t.reg_id AND LOWER(TRIM(cs.sub_type)) IN('theory','pe','oe','lab')),JSON_ARRAY())) before_json
    FROM cea_verified_regulation_targets t JOIN cea_verified_regulation_changes ch ON ch.reg_id=t.reg_id;

  UPDATE curriculum_subjects cs JOIN cea_verified_regulation_targets t ON t.reg_id=cs.reg_id
    SET cs.cie_max_marks=40,cs.see_max_marks=60
    WHERE t.program_level='PG' AND LOWER(TRIM(cs.sub_type)) IN('theory','pe','oe','lab')
      AND cs.cie_max_marks=30 AND cs.see_max_marks=70;
  SET v_repair_count=ROW_COUNT();

  INSERT INTO academic_settings(reg_id,regulation_code,setting_key,setting_value,data_type,category,is_editable,description,updated_by)
    SELECT v.reg_id,v.regulation_code,v.setting_key,v.setting_value,v.data_type,v.category,v.is_editable,v.description,v_actor
    FROM cea_verified_regulation_values v JOIN cea_verified_regulation_changes ch ON ch.reg_id=v.reg_id
    ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),data_type=VALUES(data_type),category=VALUES(category),
      is_editable=VALUES(is_editable),description=VALUES(description),updated_by=VALUES(updated_by);

  INSERT INTO academic_record_snapshots(entity_type,entity_id,department_id,actor_user_id,reason,before_json,after_json)
    SELECT 'REGULATION_CONFIG',CAST(t.reg_id AS CHAR),sp.dept_id,v_actor,v_reason,b.before_json,
      JSON_OBJECT('settings',COALESCE((SELECT JSON_ARRAYAGG(JSON_OBJECT('id',a.id,'setting_key',a.setting_key,
          'setting_value',a.setting_value,'data_type',a.data_type,'category',a.category,'is_editable',a.is_editable))
          FROM academic_settings a WHERE a.reg_id=t.reg_id),JSON_ARRAY()),
        'curriculum',COALESCE((SELECT JSON_ARRAYAGG(JSON_OBJECT('id',cs.id,'spec_id',cs.spec_id,'sub_type',cs.sub_type,
          'cie_max_marks',cs.cie_max_marks,'see_max_marks',cs.see_max_marks)) FROM curriculum_subjects cs
          WHERE cs.reg_id=t.reg_id AND LOWER(TRIM(cs.sub_type)) IN('theory','pe','oe','lab')),JSON_ARRAY()),
        'source',t.source_name,'sha256',t.source_sha256,'section',t.source_section,
        'rounding_authority','User: nearest whole final PG CIA mark',
        'btech_equivalence_authority','User: B.Tech R20 and R26 treated as R23')
    FROM cea_verified_regulation_targets t JOIN cea_verified_regulation_before b ON b.reg_id=t.reg_id
    JOIN (SELECT DISTINCT prog_id,dept_id FROM specialization) sp ON sp.prog_id=t.prog_id;

  COMMIT;
  DROP TEMPORARY TABLE cea_verified_regulation_before;
  DROP TEMPORARY TABLE cea_verified_regulation_changes;
  DROP TEMPORARY TABLE cea_verified_regulation_values;
  DROP TEMPORARY TABLE cea_verified_regulation_targets;
  SELECT v_reg_count configured_regulations,v_repair_count repaired_curriculum_maxima;
END$$
DELIMITER ;
CALL cea_apply_verified_regulations_20261015();
DROP PROCEDURE cea_apply_verified_regulations_20261015;
DROP TEMPORARY TABLE cea_verified_regulation_catalog;
