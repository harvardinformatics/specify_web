#!/bin/bash
cd "$(dirname "$0")"

BASE_DIR="/mnt/huhimagestorage/Herbaria6/HUH-IT/from_informatics_*/Mass_Digitization/*/*"
IMG_DIR="/mnt/huhimagestorage/huhspecimenimages"
S3DIR="s3://huhspecimenimages"

# Make sure that the filesystem is mounted correctly with mfsymlinks (CIFS only?)
content=$(cat "$IMG_DIR/symlink_testfile.txt")
if [ "Symlinks working!" != "$content" ];then
    echo "ERROR: huhimagestorage/ is not mounted with mfsymlinks"
    exit 1
fi

#USERS=(abrach amilby bfranzone cthornton dhanrahan erullo etaylor hmerchant iferreras kbrzezinski wkittredge mschill zbailey etanner NEBC)
SESSION_FORMAT="^20[0-9]{2}-[0-9]{2}-[0-9]{2}[-A-Za-z0-9_]*$"
BARCODE_FORMAT="^[0-9]{8}$"

for sd in $BASE_DIR ; do # iterate through all image session directories

  if [ ! -d $sd ] ; then
  	continue
  fi

  if [[ ! $(basename $sd) =~ $SESSION_FORMAT ]] ; then
    echo "WARN: Skipping badly formatted directory $sd"
    continue
  fi

	echo "Checking $sd"

	if [ -f "$sd/ingest_done" ] ; then
		#echo "Done file found, skipping $sd"
		continue
	fi

  if [ -f "$sd/ingest_error" ] ; then
    echo "Error file found, skipping $sd"
    continue
  fi

	if [ ! -d "$sd/Output/JPG" ] ; then
		echo "WARN: No JPG directory, skipping $sd"
		continue
	fi

	if [ ! -d "$sd/Output/JPG-Preview" ] ; then
		echo "WARN: No JPG-Preview directory, skipping $sd"
		continue
	fi

	if [ ! -d "$sd/Output/JPG-Thumbnail" ] ; then
		echo "WARN: No JPG-Thumbnail directory, skipping $sd"
		continue
	fi

	if [ ! -d "$sd/Output/DNG" ] ; then
		echo "WARN: No DNG directory, skipping $sd"
		continue
	fi

	./image_count_check.sh $sd || { echo "ERROR: Check for derivatives failed, skipping $sd" ; continue ; }

	BATCH_ID=$(php add_image_batch.php $sd) || { echo "ERROR: Failed to add image batch for $sd" ; exit 1 ; }
	echo "Added/found batch (id ${BATCH_ID}) for $sd"

  # Start caching barcodes
  echo "Caching barcodes for $sd"
  ./cache_barcodes.sh "$sd" 8 &

	for f in $sd/Output/JPG/*.jpg ; do # use the JPGs for scanning for barcodes

		filename=$(basename -- "$f")
		basefile="${filename%.*}"
		masterfile="$sd/Capture/$basefile.CR2"

		echo "Looking for barcodes in $f"
		#BARCODES=$(zbarimg -q --raw -Sdisable -Scode39.enable "$f")
    BARCODES=$(./runcached.sh --ttl 86400 --cache-dir /tmp/zbarcache --ignore-env --ignore-pwd --prune-cache zbarimg -q --raw -Sdisable -Scode39.enable "$f")
		echo "Found ${BARCODES[*]}"

		# scrub barcodes
		sc_barcodes=()
		for b in ${BARCODES[@]} ; do
			if [[ $b =~ $BARCODE_FORMAT ]] ; then
				sc_barcodes+=($b)
			else
				echo "WARN: Skipping bad barcode ($b) from $f"
				continue
			fi
		done

		barcode_list=$(IFS=';' ; echo "${sc_barcodes[*]}" ; )

		# Create image_set records that all derivates will hang from
		imagesetid=$(php add_image_set.php "$BATCH_ID" "$masterfile" ${sc_barcodes[*]}) || { echo "ERROR: Failed to add image_set record for $masterfile" ; exit 1; }
    #echo "Added/found image_set (id $imagesetid) for $masterfile"

		# Create image_object records for the base files (before the symlinks)
		imagefile="$sd/Output/JPG/$basefile.jpg"
    jpgid=$(php add_image_object.php "$imagesetid" "$imagefile" 4 0 "$barcode_list") || { echo "ERROR: add_image_object.php failed for $imagefile" ; exit 1; }
		imagefile="$sd/Output/JPG-Preview/$basefile.jpg"
		jpgpid=$(php add_image_object.php "$imagesetid" "$imagefile" 3 0 "$barcode_list") || { echo "ERROR: add_image_object.php failed for $imagefile" ; exit 1; }
		imagefile="$sd/Output/JPG-Thumbnail/$basefile.jpg"
		jpgtid=$(php add_image_object.php "$imagesetid" "$imagefile" 2 0 "$barcode_list") || { echo "ERROR: add_image_object.php failed for $imagefile" ; exit 1; }
		imagefile="$sd/Output/DNG/$basefile.dng"
		dngid=$(php add_image_object.php "$imagesetid" "$imagefile" 7 0 "$barcode_list") || { echo "ERROR: add_image_object.php failed for $imagefile" ; exit 1; }
		imagefile="$sd/Capture/$basefile.CR2"
		cr2id=$(php add_image_object.php "$imagesetid" "$imagefile" 8 0 "$barcode_list") || { echo "ERROR: add_image_object.php failed for $imagefile" ; exit 1; }

    # Add random hash in place of barcode if none found
    if [ ${#sc_barcodes[@]} -eq 0 ]; then
        # generate a random string
        r=$(echo -n "$sd/Output/JPG-Preview/$basefile.jpg" | md5sum | cut -d' ' -f1)
        sc_barcodes+=("nobarcode_$r")
    fi

		for b in ${sc_barcodes[@]} ; do

      # if it's a nobarcode hash, don't put that in the barcode fields of the database
      bc=$b
      if [[ $b =~ ^nobarcode_ ]] ; then
        bc=""
      fi

			# Create symlinks for all of the files
      target="$sd/Output/JPG/$basefile.jpg"
			linkfile=$(./link_image.sh "$target" "$IMG_DIR/JPG" "$b" "jpg") || { echo "ERROR: link_image.sh failed for $b ($linkfile)" ; exit 1; }
      echo "linked $target to $linkfile"
      ./aws_upload.sh "$linkfile" "$S3DIR/JPG/" &
			#aws s3 cp --no-progress --quiet "$linkfile" "$S3DIR/JPG/" &
			php copy_image_object.php "$jpgid" "$linkfile" 1 "$bc" || { echo "ERROR: copy_image_object.php failed for $linkfile" ; exit 1; }

      target="$sd/Output/JPG-Preview/$basefile.jpg"
			linkfile=$(./link_image.sh "$target" "$IMG_DIR/JPG-Preview" "$b" "jpg") || { echo "ERROR: link_image.sh failed for $b ($linkfile)" ; exit 1; }
      echo "linked $target to $linkfile"
      ./aws_upload.sh "$linkfile" "$S3DIR/JPG-Preview/" &
			#aws s3 cp --no-progress --quiet "$linkfile" "$S3DIR/JPG-Preview/" &
			php copy_image_object.php "$jpgpid" "$linkfile" 1 "$bc" || { echo "ERROR: copy_image_object.php failed for $linkfile" ; exit 1; }

      target="$sd/Output/JPG-Thumbnail/$basefile.jpg"
			linkfile=$(./link_image.sh "$target" "$IMG_DIR/JPG-Thumbnail" "$b" "jpg") || { echo "ERROR: link_image.sh failed for $b ($linkfile)" ; exit 1; }
      echo "linked $target to $linkfile"
      ./aws_upload.sh "$linkfile" "$S3DIR/JPG-Thumbnail/" &
			#aws s3 cp --no-progress --quiet "$linkfile" "$S3DIR/JPG-Thumbnail/" &
			php copy_image_object.php "$jpgtid" "$linkfile" 1 "$bc" || { echo "ERROR: copy_image_object.php failed for $linkfile" ; exit 1; }

      target="$sd/Output/DNG/$basefile.dng"
			linkfile=$(./link_image.sh "$target" "$IMG_DIR/DNG" "$b" "dng") || { echo "ERROR: link_image.sh failed for $b ($linkfile)" ; exit 1; }
      echo "linked $target to $linkfile"
      ./aws_upload.sh "$linkfile" "$S3DIR/DNG/" &
			#aws s3 cp --no-progress --quiet "$linkfile" "$S3DIR/DNG/" &
			php copy_image_object.php "$dngid" "$linkfile" 0 "$bc" || { echo "ERROR: copy_image_object.php failed for $linkfile" ; exit 1; }

      target="$sd/Capture/$basefile.CR2"
			linkfile=$(./link_image.sh "$target" "$IMG_DIR/RAW" "$b" "CR2") || { echo "ERROR: link_image.sh failed for $b ($linkfile)" ; exit 1; }
      echo "linked $target to $linkfile"
      #./aws_upload.sh "$linkfile" "$S3DIR/RAW/" &
			#aws s3 cp --no-progress --quiet "$linkfile" "$S3DIR/RAW/" &
			php copy_image_object.php "$cr2id" "$linkfile" 0 "$bc" || { echo "ERROR: copy_image_object.php failed for $linkfile" ; exit 1; }

		done

	done

  # Create tr_batch records for transcription app
  trbatchid=$(php add_tr_batch.php "$BATCH_ID") || { echo "ERROR: Failed to add tr_batch for $masterfile" ; exit 1; }
  echo "Added TR_BATCH (id $trbatchid) for $masterfile"

	touch $sd/ingest_done # so we don't reprocess this directory

	echo "Done ingesting $sd"

done
