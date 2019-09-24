delimiter $$

DROP PROCEDURE IF EXISTS setup_tr_batch $$
CREATE PROCEDURE setup_tr_batch(tr_batch_id BIGINT, trbatchname VARCHAR(100))
  BEGIN
	DECLARE trbatchid BIGINT DEFAULT 0;
    DECLARE imageobjectid BIGINT DEFAULT 0;
    DECLARE barcodeval VARCHAR(255);
    DECLARE barcode_count INT DEFAULT 0;
    DECLARE i INT DEFAULT 0;
    DECLARE position INT DEFAULT 0;
    DECLARE split_barcode VARCHAR(255);
    DECLARE done INT DEFAULT 0;
    DECLARE cur CURSOR FOR SELECT trb.tr_batch_id, imo.id, imo.barcodes 
        from TR_BATCH trb, IMAGE_BATCH imb, IMAGE_SET ims, IMAGE_OBJECT imo, IMAGE_LOCAL_FILE imlf
        where
        trb.image_batch_id = imb.id 
        and imb.id = ims.batch_id
        and ims.id = imo.image_set_id
        and imlf.id = imo.image_local_file_id
        and imo.object_type_id = '3'
        and imlf.path not like 'TranscriptionApp%'
        and imlf.path not like 'huhimagestorage/huhspecimenimages/%'
        and trb.tr_batch_id = tr_batch_id
        order by imlf.filename;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    OPEN cur;
      read_loop: LOOP
        FETCH cur INTO trbatchid, imageobjectid, barcodeval;
        IF done THEN
          LEAVE read_loop;
        END IF;

        IF NULLIF(barcodeval, '') IS NULL THEN

          SET position = position + 1;
          INSERT INTO TR_BATCH_IMAGE VALUES (trbatchid, imageobjectid, NULL, position);
        ELSE
          SET barcode_count = (SELECT CHAR_LENGTH(barcodeval) -
                           CHAR_LENGTH(REPLACE(barcodeval, ';', '')) + 1);
                           
          SET i=1;
          WHILE i <= barcode_count DO
            SET position = position + 1;
            SET split_barcode = (SELECT TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(barcodeval, ';', i), ';', -1)));
            INSERT INTO TR_BATCH_IMAGE VALUES (trbatchid, imageobjectid, split_barcode, position);
            SET i = i + 1;
          END WHILE;
        END IF;
        
      END LOOP;

    CLOSE cur;
  END; $$

delimiter ;
