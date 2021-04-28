-- Create transcription batches for all batch directories that do not already exist in the TR_BATCH table
insert into TR_BATCH (path, image_batch_id) 
  select SUBSTRING_INDEX(imb.batch_name,'/',-2) as path, imb.id from IMAGE_BATCH imb where imb.id not in (select image_batch_id from TR_BATCH where image_batch_id is not null) and imb.batch_name like 'from_informatics_%/Mass_Digitization%'
  -- select SUBSTRING_INDEX(imb.batch_name,'/',-2) as path, imb.id from IMAGE_BATCH imb where imb.id not in (select image_batch_id from TR_BATCH where image_batch_id is not null) and imb.batch_name like 'from_informatics_%'
;
call call_tr_setup;

-- check on batches
select tr.*, count(*) from TR_BATCH tr, TR_BATCH_IMAGE tri where tr.tr_batch_id = tri.tr_batch_id group by tr.tr_batch_id order by tr_batch_id desc;
select * from TR_BATCH order by tr_batch_id desc;

-- cleanup partial batch in processing
delete from TR_BATCH where tr_batch_id = 2628;
delete from TR_BATCH_IMAGE where tr_batch_id not in (select tr_batch_id from TR_BATCH);



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

select * from IMAGE_BATCH where batch_name like '%11-06%';

select * from TR_BATCH_IMAGE where tr_batch_id = 2611; -- 01946689
select * from IMAGE_OBJECT where id = 9839999;
select * from IMAGE_SET_collectionobject where imagesetid = 1433696;
select * from fragment where identifier = '01946689';

select * from IMAGE_SET_collectionobject where imagesetid = 1433696;
insert into IMAGE_SET_collectionobject (imagesetid, collectionobjectid) values (1433696, 1317737);

select * from TR_BATCH order by tr_batch_id desc;

-- 2770 3103 3106 3302 3454 3469

select * from TR_BATCH where tr_batch_id IN (2770,3103,3106,3302,3454,3469);
delete from TR_BATCH_IMAGE where tr_batch_id >= 2770;

select count(*) c, trb.tr_batch_id, trb.path from TR_BATCH trb, TR_BATCH_IMAGE trbi where trb.tr_batch_id >= 2770 and trb.tr_batch_id = trbi.tr_batch_id 
and trbi.barcode is not null and trbi.barcode not in (select identifier from fragment where identifier is not null)
group by trb.tr_batch_id
order by c asc;

select * from TR_BATCH_IMAGE where tr_batch_id = 3206 and barcode not in (select identifier from fragment where identifier is not null);
-- 3357, 3206

update TR_BATCH set completed_date = '2020-01-01' where tr_batch_id IN (3224,3188,3439,3373,3334,3252,3285,3399,3346,3371,3458,3235,3402,3345,3356,3410,3268,3444,3210,3354,3394,3342,3265,3184,3248,3141,3231,3414,3393,3260,3295,3060,3276,3416,3385,3447,3127,3466,3351,3275,3421,3383,3180,3449,3364,3243,3118,3468,3274,3433,3337,3303,3242,3117,3469,3349,3273,3437,3254,3239,3107,3377,3335,3178,3322,3237,3271,3300,3176,3358,3321,3236,3198,3311,3270,3440,3212,3283,3222,3186,3282,3309,3221,3185,3331,3297,3249,3460,3280,3196,3308,3220,3445,3462,3319,3195,3306,3446,3247,3464,3340,3305,3259,3244,3229,3217,3228,3192,3452,3191,3378,3338,3302,3214,3361,3348,3225,3189,3253,3213,3288,3099,3347,3457,3187,3333,3320,3269,3459,3203,3234,3343,3318,3232,3412,3341,3183,3352,3218,3181,3339,3304,3257,3325,3379,3278,3324,3226,3179,3453,3323,3202,3314,3272,3455,3201,3312,3251,3310,3441,3298,3197,3208,3193,3255,3296,3230,3350,3216,3292,3223,3299,3199,3207,3313,3219,3316,3290,2770,3363,3291,3173,3211,3317,3294,3438,3329,3360,3327,3279,3332,3194,3293,3250,3205,3328,3147,3357,3206,3369,3353,3315,3204);
update TR_BATCH set completed_date = '2020-01-01' where tr_batch_id >= 2770 and tr_batch_id not in (3172,3368,3365,3367,3366);

select * from TR_BATCH order by tr_batch_id desc;



