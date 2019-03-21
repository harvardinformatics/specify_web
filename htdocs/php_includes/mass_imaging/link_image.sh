#!/bin/bash

if [ "$1" == "" ] || [ "$2" == "" ] || [ "$3" == "" ] || [ "$4" == "" ]; then
	echo "USAGE: link_image.sh <sourcefile> <linkdir> <barcode> <extension>";
	exit 1;
fi

sourcefile=$1
linkdir=$2
barcode=$3
ext=$4
linkfile="$linkdir/$barcode.$ext"

if [ -f "$linkfile" ] ; then
	
	if [ "$linkfile" -ef "$sourcefile" ] ; then
		#echo "WARN: symlink already exists at $linkfile for $sourcefile"
		:
	else
		for x in {a..z} ; do
			linkfile="$linkdir/${barcode}_$x.$ext"
			if [ -f "$linkfile" ] ; then
				if [ "$linkfile" -ef "$sourcefile" ] ; then
					#echo "WARN: symlink already exists at $linkfile for $sourcefile"
					break
				else
					continue
				fi
			else
			
				ln -sr "$sourcefile" "$linkfile"
				
				if [ $? != 0 ] ; then
					echo "ERROR: Symlink failed for $linkfile to $sourcefile"
					exit 1;
				fi	
				
				#echo "Linked $linkfile to $sourcefile"
				break			
			fi									
		done

	fi
else

	ln -sr "$sourcefile" "$linkfile"
	
	if [ $? != 0 ] ; then
		echo "ERROR: Symlink failed for $linkfile to $sourcefile"
		exit 1;
	fi		
	
	#echo "Linked $linkfile to $sourcefile"
fi

# print final linkfile as the result
echo "$linkfile"