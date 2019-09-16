-- Mark batches as done if they are not SoRo and have records
update TR_BATCH set completed_date = now() 
-- select * from TR_BATCH
where completed_date is null 
and path not like 'SoRo%' 
and path not like 'FIXME%'
and tr_batch_id not in (select tr_batch_id from TR_BATCH_IMAGE where barcode not in (select identifier from fragment where identifier is not null))
;