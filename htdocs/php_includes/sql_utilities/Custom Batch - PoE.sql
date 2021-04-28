-- create batch
insert into TR_BATCH (path) values ('PoE: Other'); -- 5424
select * from TR_BATCH order by tr_batch_id desc; -- get batch id for below
-- 5425

-- if based on geography, get geography node range
-- select * from geography where name = 'California'; -- node 7411 to 7584

-- if based on txonomy, get taxonomy node range
-- select * from taxon where name = 'Orchidaceae';
-- 211418 to 229803

select nodenumber, highestchildnodenumber from taxon where name in ('Agavaceae', 'Apocynaceae', 'Araceae', 'Asphodelaceae', 'Bromeliaceae', 'Cactaceae', 'Crassulaceae', 'Droseraceae', 'Euphorbiaceae', 'Lentibulariaceae', 'Nepenthaceae', 'Piperaceae', 'Sarraceniaceae');

-- main function, insert records - modify conditions as needed
SELECT @i:=0;
insert into TR_BATCH_IMAGE (tr_batch_id, image_object_id, barcode, position) 
     select 5425, imo.ID, f.identifier, @i:=@i+1
from fragment f, IMAGE_SET_collectionobject imsc, IMAGE_OBJECT imo, collectionobject co, collectingevent ce, collector c, locality l, geography g, determination d, taxon t
where f.collectionobjectid = imsc.collectionobjectid
  and imsc.imagesetid = imo.IMAGE_SET_ID
  and imo.active_flag = 1
  and imo.object_type_id = 3
-- modify below here for batch conditions
  and d.fragmentid = f.fragmentid
  and d.taxonid = t.taxonid
  and f.collectionobjectid = co.collectionobjectid
  and co.collectingeventid = ce.collectingeventid
  and ce.collectingeventid = c.collectingeventid
  and ce.localityid = l.localityid
  and l.geographyid = g.geographyid
  and (l.localityname = '[data not captured]' or c.agentid = 97236) -- agentid for [data not captured] = 97236
  and d.yesno3 = '1' -- filedunder = true
  -- and g.nodenumber <=
  -- and g.nodenumber >=
  and ((t.nodenumber >= 3276 and t.nodenumber <= 3528) 
    or (t.nodenumber >= 11139 and t.nodenumber <= 13036) 
    or (t.nodenumber >= 13550 and t.nodenumber <= 15288) 
    or (t.nodenumber >= 60157 and t.nodenumber <= 61259) 
    or (t.nodenumber >= 62905 and t.nodenumber <= 64649) 
    or (t.nodenumber >= 95371 and t.nodenumber <= 96626) 
    or (t.nodenumber >= 118381 and t.nodenumber <= 118492) 
    or (t.nodenumber >= 124539 and t.nodenumber <= 130885) 
    or (t.nodenumber >= 174337 and t.nodenumber <= 174614) 
    or (t.nodenumber >= 205517 and t.nodenumber <= 205586) 
    or (t.nodenumber >= 244827 and t.nodenumber <= 247104) 
    or (t.nodenumber >= 306208 and t.nodenumber <= 306231))

order by t.fullname, g.nodenumber, f.identifier
; 
  
-- check count
select tr.*, count(*) from TR_BATCH tr, TR_BATCH_IMAGE tri where tr.tr_batch_id = tri.tr_batch_id group by tr.tr_batch_id order by tr_batch_id desc;

-- delete batch images if there is a mistake
delete from TR_BATCH_IMAGE where tr_batch_id = 5425;