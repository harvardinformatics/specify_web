-- Create transcription batches for all batch directories that do not already exist in the TR_BATCH table
insert into TR_BATCH (path, image_batch_id) 
  select SUBSTRING_INDEX(imb.batch_name,'/',-2) as path, imb.id from IMAGE_BATCH imb where imb.id not in (select image_batch_id from TR_BATCH) and imb.batch_name like 'from_informatics_%' and imb.remarks not like 'IN PROCESS';
;
call call_tr_setup;

-- check on batches
select * from TR_BATCH order by tr_batch_id desc;
select * from IMAGE_BATCH order by id desc;


-- Create trigger for position
delimiter $$
CREATE TRIGGER ins_tr_batch_image_position BEFORE INSERT ON TR_BATCH_IMAGE
FOR EACH ROW BEGIN
    SET NEW.position = (SELECT coalesce(max(position), 0) + 1  FROM TR_BATCH_IMAGE WHERE tr_batch_id = NEW.tr_batch_id);
END; $$
delimiter ; 

-- Create FIXME batches for all batches that have barcodes without a record
insert into TR_BATCH (path, image_batch_id) 
  select concat('FIXME: ', trb.path) as path, trb.image_batch_id from TR_BATCH trb where trb.image_batch_id not in (select image_batch_id from TR_BATCH where path like 'FIXME%')
; 
-- insert records where the barcode is missing from the fragment table
insert ignore into TR_BATCH_IMAGE (tr_batch_id, image_object_id, barcode)
	select trb.tr_batch_id, trbi.image_object_id, trbi.barcode from TR_BATCH trb, TR_BATCH trb2, TR_BATCH_IMAGE trbi where trb.path like 'FIXME%' and substr(trb.path,8) = trb2.path and trb2.tr_batch_id = trbi.tr_batch_id and (trbi.barcode is null or trbi.barcode not in (select identifier from fragment where identifier is not null))
;
-- delete batches with no records to fix
delete from TR_BATCH where path like 'FIXME%' and tr_batch_id not in (select tr_batch_id from TR_BATCH_IMAGE where barcode is not null);
delete from TR_BATCH_IMAGE where tr_batch_id not in (select tr_batch_id from TR_BATCH);


-- 

-- Look for empty TR_BATCH records
select count(*) from TR_BATCH;
select * from TR_BATCH where tr_batch_id not in (select tr_batch_id from TR_BATCH_IMAGE);

-- Look for empty IMAGE_BATCH records
select * from IMAGE_BATCH where ID not in (select BATCH_ID from IMAGE_SET);