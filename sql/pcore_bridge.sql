-- PulseCore bridge schema and stored procedures

CREATE SCHEMA IF NOT EXISTS pcore_bridge;
USE pcore_bridge;

DELIMITER $$
CREATE PROCEDURE sp_attach_note_to_nova(IN p_nova_id INT, IN p_note TEXT)
BEGIN
  -- adjust table name nova_notes to match real schema
  INSERT INTO pulsecore.nova_notes(nova_id,note,created_at) VALUES(p_nova_id,p_note,NOW());
END $$

CREATE PROCEDURE sp_flag_nova(IN p_nova_id INT, IN p_flag VARCHAR(32))
BEGIN
  UPDATE pulsecore.novas SET flag=p_flag WHERE id=p_nova_id;
END $$

CREATE PROCEDURE sp_upsert_analysis(IN p_climax_id INT, IN p_analysis TEXT)
BEGIN
  INSERT INTO pulsecore.climax_analysis(climax_id,analysis,updated_at) VALUES(p_climax_id,p_analysis,NOW())
  ON DUPLICATE KEY UPDATE analysis=VALUES(analysis),updated_at=VALUES(updated_at);
END $$
DELIMITER ;

-- Least privilege users
CREATE USER 'pcore_writer'@'%' IDENTIFIED BY 'PCORE_WRITER_PASS';
GRANT EXECUTE ON pcore_bridge.* TO 'pcore_writer'@'%';

CREATE USER 'pcore_reader'@'%' IDENTIFIED BY 'PCORE_READER_PASS';
GRANT SELECT ON pulsecore.novas TO 'pcore_reader'@'%';
GRANT SELECT ON pulsecore.climax_groups TO 'pcore_reader'@'%';
