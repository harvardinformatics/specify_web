#!/bin/bash

BASE_DIR="/Volumes/STAGING/./from_informatics_*/Mass_Digitization/*/*"
RSYNC_DEST="/Volumes/huh/lab/Herbaria6/HUH-IT/"

SESSION_FORMAT1="/20[0-9]{2}-[0-9]{2}-[0-9]{2}$"
SESSION_FORMAT2="/20[0-9]{2}-[0-9]{2}-[0-9]{2}-[0-9]{2}$"

dirs_to_rename=()

echo "Starting at $(date)"

for d in $BASE_DIR ; do # iterate through the directories for each photostation

  if [ ! -d $d ] ; then
    continue
  fi

  if [[ ! -d "$d/Output/JPG" || ! -d "$d/Output/JPG-Preview" || ! -d "$d/Output/JPG-Thumbnail" || ! -d "$d/Output/DNG" ]] ; then
    echo "Not ready: $d"
    continue
  fi

  if [[ ! $d =~ $SESSION_FORMAT1 && ! $d =~ $SESSION_FORMAT2 ]] ; then
    dirs_to_rename+=("$d")
  fi

  ./image_count_check.sh $d || { echo "WARN: Check for derivatives failed, skipping $d" ; continue ; }

  echo "Executing: rsync -avhR --progress --remove-source-files $d $RSYNC_DEST"
  rsync -avhR --progress --remove-source-files "$d" "$RSYNC_DEST" && find -d "$d" -empty -delete

done

if [[ ! -z $dirs_to_rename ]] ; then

  echo ""
  echo "Directories to rename:"
  printf '%s\n' "${dirs_to_rename[@]}"

fi

echo ""
echo "Finished at $(date)"
