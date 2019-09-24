#!/bin/bash

TDIR="/mnt/huhimagestorage/Herbaria6/HUH-IT/TranscriptionApp"
BARCODE_FORMAT="^[0-9]{8}$"

if [ "$1" == "" ] ; then
	echo "USAGE: setup_transcription_batch.sh <sessiondir>"
	exit 1
fi

sd=${1%/}

if [[ ! -d "$sd" ]] ; then
	echo "ERROR: Directory not found $sd"
	exit 1
fi

#if [[ ! -e "$sd/ingest_done" ]] ; then
#	echo "ERROR: Missing ingest_done file in $sd"
#	exit 1
#fi

echo "sd=$sd"
IFSb=$IFS ; IFS=/ ; read -r -a pathparts <<<"$sd" ; IFS=$IFSb ;
echo "pathparts=$pathparts"
len=${#pathparts[@]}
echo "len=$len"
session=${pathparts[$len - 1]}
user=${pathparts[$len - 2]}
t_dir="$TDIR/$user/$session"
echo "t_dir=$t_dir"

if [ -e "$t_dir" ] ; then
	echo "ERROR: Transcription directory already exists: $t_dir"
	exit 1;
fi

mkdir -p "$t_dir"

for f in $sd/Output/JPG/*.jpg ; do

	filename=$(basename -- "$f")
	basefile="${filename%.*}"
	masterfile="$sd/Capture/$basefile.CR2"
						
	echo "Looking for barcodes in $f"
	BARCODES=$(zbarimg -q --raw -Sdisable -Scode39.enable "$f") 
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
	echo "Added/found image_set (id $imagesetid) for $masterfile"
								
	barcode_count=0
						
	for b in ${sc_barcodes[@]} ; do			
		
		# link preview file to transcription dir
		linkfile="$t_dir/$basefile b${b}.jpg"
		#FIXME ln -sr "$sd/Output/JPG-Preview/$basefile.jpg" "$linkfile"
		ln -s "$sd/Output/JPG-Preview/$basefile.jpg" "$linkfile"			
		php add_image_object.php "$imagesetid" "$linkfile" 3 0 "$b" || { echo "ERROR: add_image_object.php failed for $linkfile" ; exit 1; }

		((barcode_count++))		
	done
						
	if [ $barcode_count == 0 ] ; then
		# link preview file to transcription dir with no barcode (folders usually)
		linkfile="$t_dir/$basefile.jpg"
		#FIXME ln -sr "$sd/Output/JPG-Preview/$basefile.jpg" "$linkfile"
		ln -s "$sd/Output/JPG-Preview/$basefile.jpg" "$linkfile"
		php add_image_object.php "$imagesetid" "$linkfile" 3 0 || { echo "ERROR: add_image_object.php failed for $linkfile" ; exit 1; }				
	fi
		
done

# Create TR_BATCH record
# not built ./add_tr_batch.php "TranscriptionApp/$user/$session"

echo $t_dir
