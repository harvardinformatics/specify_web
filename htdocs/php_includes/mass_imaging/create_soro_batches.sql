select * from TR_BATCH where path like 'SoRo:%';

select * from TR_BATCH_IMAGE where tr_batch_id = 5206;


SELECT imo.id id, f.identifier bc
		from fragment f, collectionobject co, collectingevent ce, collector c, locality l, geography g, geography g2, IMAGE_SET_collectionobject imsc, IMAGE_SET ims, IMAGE_OBJECT imo, IMAGE_LOCAL_FILE imlf
        where f.collectionobjectid = imsc.collectionobjectid
          and imsc.imagesetid = ims.ID
          and ims.ID = imo.IMAGE_SET_ID
          and imo.image_local_file_id = imlf.id
          and imo.object_type_id = '3'
          and imlf.path not like 'huhimagestorage/huhspecimenimages/%'
          and f.identifier is not null
          and f.collectionobjectid = co.collectionobjectid
          and co.collectingeventid = ce.collectingeventid
          and co.collectingeventid = c.collectingeventid
          and ce.localityid = l.localityid 
		  and l.geographyid = g.geographyid
          and c.agentid = 97236
		  and g.nodenumber >= g2.nodenumber
          and g.nodenumber < g2.highestchildnodenumber
          and g2.name = 'Arizona'
          order by f.identifier
;
          

SET @row_number = 0; 
insert into TR_BATCH_IMAGE (tr_batch_id, image_object_id, barcode, position)
select 5212, id, bc, (@row_number:=@row_number + 1) AS num from (
SELECT imo.id id, f.identifier bc
		from fragment f, collectionobject co, collectingevent ce, collector c, locality l, geography g, geography g2, IMAGE_SET_collectionobject imsc, IMAGE_SET ims, IMAGE_OBJECT imo, IMAGE_LOCAL_FILE imlf
        where f.collectionobjectid = imsc.collectionobjectid
          and imsc.imagesetid = ims.ID
          and ims.ID = imo.IMAGE_SET_ID
          and imo.image_local_file_id = imlf.id
          and imo.object_type_id = '3'
          and imlf.path not like 'huhimagestorage/huhspecimenimages/%'
          and f.identifier is not null
          and f.collectionobjectid = co.collectionobjectid
          and co.collectingeventid = ce.collectingeventid
          and co.collectingeventid = c.collectingeventid
          and ce.localityid = l.localityid 
		  and l.geographyid = g.geographyid
          and c.agentid = 97236
		  and g.nodenumber >= g2.nodenumber
          and g.nodenumber < g2.highestchildnodenumber
          and g2.name = 'Wyoming'
          order by f.identifier) t
;	

select tr_batch_id, count(*) from TR_BATCH_IMAGE group by tr_batch_id;
select * from TR_BATCH order by tr_batch_id desc;

