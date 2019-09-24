#!/bin/bash

BASE_DIR="/mnt/huhimagestorage/Herbaria6/HUH-IT/from_informatics_*"
IMG_DIR="/mnt/huhimagestorage/huhspecimenimages"
S3DIR="s3://huhspecimenimages"

# Make sure that the filesystem is mounted correctly with mfsymlinks (CIFS only?)
content=$(cat "$IMG_DIR/symlink_testfile.txt")
if [ "Symlinks working!" != "$content" ];then
    echo "ERROR: huhimagestorage/ is not mounted with mfsymlinks"
    exit 1
fi

USERS=(abrach bfranzone cthornton dhanrahan erullo etaylor hmerchant iferreras kbrzezinski wkittredge)
SESSION_FORMAT1="/20[0-9]{2}-[0-9]{2}-[0-9]{2}$" ;
SESSION_FORMAT2="/20[0-9]{2}-[0-9]{2}-[0-9]{2}-[0-9]{2}$" ;
SESSION_FORMAT3="/20[0-9]{2}_[0-9]{2}_[0-9]{2}$" ;
SESSION_FORMAT4="/20[0-9]{2}_[0-9]{2}_[0-9]{2}-[0-9]{2}$" ;
SESSION_FORMAT5="/20[0-9]{2}.[0-9]{2}.[0-9]{2}$" ;
SESSION_FORMAT6="/20[0-9]{2}.[0-9]{2}.[0-9]{2}-[0-9]{2}$" ;
SESSION_FORMAT5="/[0-9]{1,2}-[0-9]{1,2}-[0-9]{1,2}$" ;
SESSION_FORMAT6="/[0-9]{1,2}-[0-9]{1,2}-[0-9]{1,2}-[0-9]{2}$" ;
BARCODE_FORMAT="^[0-9]{8}$" ;

for d in $BASE_DIR ; do # iterate through the directories for each photostation
	for u in ${USERS[@]} ; do # iterate through the subdirectories for each user
		DIR=$d/$u
		if [ -d $DIR ]; then # user may or may not exists for that photostation
			for sd in $DIR/* ; do # iterate through all subdirectories (each photo session)

				if [ ! -d $sd ] ; then
					continue
				fi

				if true ; then
				#if [[ $sd =~ $SESSION_FORMAT1 || $sd =~ $SESSION_FORMAT2 || $sd =~ $SESSION_FORMAT3 || $sd =~ $SESSION_FORMAT4 || $sd =~ $SESSION_FORMAT5 || $sd =~ $SESSION_FORMAT6 ]] ; then

					echo "Checking $sd"


					if [ -f "$sd/ingest_done" ] ; then
						echo "Done file found, skipping $sd"
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

					./image_count_check.sh $sd || { echo "ERROR: Check for derivatives failed, skipping $sd" ; touch $sd/ingest_error ; continue ; }

				else
					echo "WARN: Skipping badly formatted directory $sd"
				fi

			done
		fi
	done
done
