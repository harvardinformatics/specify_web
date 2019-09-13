echo "Starting sync : $(date)"
aws s3 sync --quiet --size-only /mnt/huhimagestorage/HUHWoodSlides/ s3://huhwoodslides/
echo "Finished sync : $(date)"
