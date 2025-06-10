#!/bin/bash
for i in $(seq 1 5)
do
  aws s3 cp --no-progress --quiet "$1" "$2" || { echo "ERROR: aws cp failed for $linkfile" ; })
  result=$?

  if [[ $result -eq 0 ]]
  then
    exit 0
  else
    echo "WARN: aws cp failed for $1, retrying"
  sleep 10
fi

echo "ERROR: aws cp failed for $1, gave up."

done
