delimiter $$

DROP PROCEDURE IF EXISTS setup_tr_batch_soro_by_state $$
CREATE PROCEDURE setup_tr_batch_soro_by_state(trbatchid BIGINT, state VARCHAR(100))
  BEGIN
    DECLARE imageobjectid BIGINT DEFAULT 0;
    DECLARE barcode VARCHAR(255);
    DECLARE statelookup VARCHAR(255);
    DECLARE collectorid BIGINT DEFAULT 0;
    DECLARE position INT DEFAULT 0;
    DECLARE done INT DEFAULT 0;
    DECLARE cur CURSOR FOR SELECT imo.id, f.identifier, getGeographyOfRank(300, g.highestchildnodenumber, g.nodenumber) as st
		from fragment f, collectionobject co, collectingevent ce, collector c, locality l, geography g, IMAGE_SET_collectionobject imsc, IMAGE_SET ims, IMAGE_OBJECT imo, IMAGE_LOCAL_FILE imlf
        where f.collectionobjectid = imsc.collectionobjectid
          and imsc.imagesetid = ims.ID
          and ims.ID = imo.IMAGE_SET_ID
          and imo.image_local_file_id = imlf.id
          and imo.object_type_id = '3'
		  and imlf.path not like 'TranscriptionApp%'
          and imlf.path not like 'huhimagestorage/huhspecimenimages/%'
          and f.identifier is not null
          and f.collectionobjectid = co.collectionobjectid
          and co.collectingeventid = c.collectingeventid
          and c.agentid = 97236
		  having st = state
          order by f.identifier;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;    

    OPEN cur;
      read_loop: LOOP
        FETCH cur INTO imageobjectid, barcode;
        IF done THEN
          LEAVE read_loop;
        END IF;
            
		SET position = position + 1; 
		INSERT INTO TR_BATCH_IMAGE VALUES (trbatchid, imageobjectid, barcode, position);
        
      END LOOP;

    CLOSE cur;
  END; $$

delimiter ;


     
