-- Create SoRo batches for all batch directories that do not already exist in the TR_BATCH table

insert into TR_BATCH (path, image_batch_id) 
  select concat('SoRo: ', path), image_batch_id from TR_BATCH where image_batch_id not in (select image_batch_id from TR_BATCH where path like 'SoRo%')
;

-- delete SoRo batches that have no records - run after call_tr_setup_soro()
delete from TR_BATCH where path like 'SoRo%' and tr_batch_id not in (select tr_batch_id from TR_BATCH_IMAGE);


