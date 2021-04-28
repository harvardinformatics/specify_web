insert into TR_BATCH (path) values ('PoE: Ericaceae 6'); -- 5424
select * from TR_BATCH order by tr_batch_id desc;
-- 5426, 5427, 5428, 5429, 5430, 5431

SELECT @i:=0;
insert into TR_BATCH_IMAGE (tr_batch_id, image_object_id, barcode, position) 
     select 5431, image_object_id, barcode, @i:=@i+1
     from TR_BATCH_IMAGE
     where tr_batch_id = 5423
     and position > 10000 
     -- and position <= 10000
     ;
     
delete from TR_BATCH_IMAGE where tr_batch_id = 5426;

select * from fragment f, IMAGE_SET_collectionobject imsc, IMAGE_SET ims, IMAGE_OBJECT imo, IMAGE_LOCAL_FILE imlf
where f.collectionobjectid = imsc.collectionobjectid
  and imsc.imagesetid = ims.ID
  and imo.IMAGE_SET_ID = ims.ID
  and imlf.id = imo.image_local_file_id
  and f.identifier IN ('00395752', '00355232');
  -- and f.identifier = '00355232';
  
  
select * from TR_BATCH_IMAGE trbi, TR_BATCH trb where trbi.tr_batch_id = trb.tr_batch_id and trbi.barcode in ('01642517', '01642518', '01642519', '01642520')
  
  