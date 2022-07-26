echo "Retainful Pro pack"
current_dir="$PWD/"
echo "Current Dir $current_dir"
pack_folder="retainful-next-order-coupon-for-woocommerce"
pack_compress_folder=$current_dir$pack_folder
composer_run(){
  # shellcheck disable=SC2164
  cd "woocommerce"
  composer install
  cd ..
}
copy_folder(){
  echo "Compress Dir $pack_compress_folder"
  from_folder="woocommerce"
  from_folder_dir=$current_dir$from_folder
  move_dir=("i18n" "src" "vendor" "retainful-next-order-coupon-for-woocommerce.php" "readme.txt")
  if [ -d "$pack_compress_folder" ]
  then
      rm -r "$pack_folder"
      mkdir "$pack_folder"
      # shellcheck disable=SC2068
      for dir in ${move_dir[@]}
      do
        cp -r "$from_folder_dir/$dir" "$pack_compress_folder/$dir"
      done
  else
      mkdir "$pack_folder"
      # shellcheck disable=SC2068
      for dir in ${move_dir[@]}
      do
        cp -r "$from_folder_dir/$dir" "$pack_compress_folder/$dir"
      done
  fi
}

remove_files(){
  remove_path="vendor/jaybizzle/crawler-detect/";
  remove_folder=("export.php")
  if [ -d "$pack_compress_folder" ]
  then
    # shellcheck disable=SC2068
    for dir in ${remove_folder[@]}
    do
      rm -r "$pack_compress_folder$remove_path$dir"
    done
  fi
}

zip_folder(){
  pack_folder_name="retainful-wordpress"
  rm "$pack_folder_name".zip
  zip -r "$pack_folder_name".zip $pack_folder
  zip -d "$pack_folder_name".zip __MACOSX/\*
  zip -d "$pack_folder_name".zip \*/.DS_Store
}
composer_run
copy_folder
remove_files
#zip_folder

echo "End"
