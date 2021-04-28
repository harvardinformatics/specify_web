delimiter $$

DROP PROCEDURE IF EXISTS setup_tr_batch_soro $$
CREATE PROCEDURE setup_tr_batch_soro(tr_batch_id BIGINT, trbatchname VARCHAR(100))
  BEGIN
	DECLARE trbatchid BIGINT DEFAULT 0;
    DECLARE imageobjectid BIGINT DEFAULT 0;
    DECLARE barcodeval VARCHAR(255);
    DECLARE barcode_count INT DEFAULT 0;
    DECLARE state VARCHAR(255);
    DECLARE collectorid BIGINT DEFAULT 0;
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

        IF NOT NULLIF(barcodeval, '') IS NULL THEN

          SET barcode_count = (SELECT CHAR_LENGTH(barcodeval) -
                           CHAR_LENGTH(REPLACE(barcodeval, ';', '')) + 1);
          SET i=1;
          WHILE i <= barcode_count DO
			SET split_barcode = (SELECT TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(barcodeval, ';', i), ';', -1)));
 
-- 			SET collectorid = 0;
--            
--  			select c.agentid
--              into collectorid
--             from collector c, collectionobject co, fragment f
--             where c.agentid = 97236
-- 			   and c.collectingeventid = co.collectingeventid
--                and co.collectionobjectid = f.collectionobjectid
--                and f.identifier = split_barcode;
--               
--  			IF (collectorid = 97236 ) THEN
            
				select getGeographyOfRank(300, g.highestchildnodenumber, g.nodenumber)
				into state
				from fragment f, collectionobject co, collectingevent ce, locality l, geography g
				where f.collectionobjectid = co.collectionobjectid 
				  and co.collectingeventid = ce.collectingeventid 
				  and ce.localityid = l.localityid 
				  and l.geographyid = g.geographyid 
				  and f.identifier = split_barcode;

 				IF (state IN ('Arizona','Colorado','Kansas','Nebraska','New Mexico','Oklahoma','South Dakota','Texas','Utah','Wyoming')) THEN
					SET position = position + 1; 
					INSERT INTO TR_BATCH_IMAGE VALUES (trbatchid, imageobjectid, split_barcode, position);
 				END IF;
                
-- 			END IF ;
            
            SET i = i + 1;
          END WHILE;
        END IF;
        
      END LOOP;

    CLOSE cur;
  END; $$

delimiter ;



select * from TR_BATCH order by tr_batch_id desc;
select * from TR_BATCH where tr_batch_id = 4154;
select * from TR_BATCH_IMAGE where tr_batch_id = 4154;
delete from TR_BATCH_IMAGE where tr_batch_id = 4154;
CALL setup_tr_batch_soro(4154, 'SoRo: abrach/2017-07-31');


select * from fragment where identifier = '00273692';
select * from collectionobject where collectionobjectid = 238155;
select * from collector where collectingeventid = 238155;

select * from agent where agentid = 29935;


            select getGeographyOfRank(300, g.highestchildnodenumber, g.nodenumber)
			from fragment f, collectionobject co, collectingevent ce, locality l, geography g
			where f.collectionobjectid = co.collectionobjectid 
			  and co.collectingeventid = ce.collectingeventid 
              and ce.localityid = l.localityid 
              and l.geographyid = g.geographyid 
              and f.identifier = '00974400';

 			select c.agentid
             from collector c, collectionobject co, fragment f
             where c.agentid = 97236
			   and c.collectingeventid = co.collectingeventid
               and co.collectionobjectid = f.collectionobjectid
               and f.identifier = '00974400';
               
SELECT trb.tr_batch_id, imo.id, imo.barcodes 
        from TR_BATCH trb, IMAGE_BATCH imb, IMAGE_SET ims, IMAGE_OBJECT imo, IMAGE_LOCAL_FILE imlf
        where
        trb.image_batch_id = imb.id 
        and imb.id = ims.batch_id
        and ims.id = imo.image_set_id
        and imlf.id = imo.image_local_file_id
        and imo.object_type_id = '3'
        and imlf.path not like 'TranscriptionApp%'
        and imlf.path not like 'huhimagestorage/huhspecimenimages/%'
        and trb.tr_batch_id = 4154
        order by imlf.filename;
               
               
select * from TR_BATCH trb, TR_BATCH_IMAGE trbi where trb.tr_batch_id = trbi.tr_batch_id and trbi.barcode = '00974400';
select * from TR_BATCH where image_batch_id = 7138;
select * from TR_BATCH_IMAGE where tr_batch_id = 4154;

SELECT TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX('00974400;4444;4455', ';', 3), ';', -1));


select count(*) from TR_BATCH trb, TR_BATCH_IMAGE trbi where trb.tr_batch_id = trbi.tr_batch_id and trb.path like 'SoRo%';

delete from TR_BATCH where path like 'SoRo%';
delete from TR_BATCH_IMAGE where tr_batch_id not in (select tr_batch_id from TR_BATCH where tr_batch_id is not null);

