#!/bin/bash
echo "Retainful Pack"
current_dir="$PWD"
vendor_folder=$current_dir"/vendor"
composer_lock_file=$current_dir"/composer.lock"
pro_plugin_name="retainful-next-order-coupon-for-woocommerce"
pack_pro_folder=$current_dir"/../compressed_pack"
plugin_pro_compress_folder=$pack_pro_folder"/"$pro_plugin_name
composer_run() {
  rm "$composer_lock_file"
  rm -r "$vendor_folder"
  # shellcheck disable=SC2164
  cd "$current_dir"
  composer install --no-dev
  composer update --no-dev
  echo "Compress Done"
  # shellcheck disable=SC2164
  cd "$current_dir"
}
update_ini_file() {
  cd $current_dir
  wp i18n make-pot . "i18n/languages/$pro_plugin_name.pot" --slug="$pro_plugin_name" --domain="$pro_plugin_name" --include=$pro_plugin_name".php",/src/ --headers='{"Last-Translator":"Retainful <support@retainful.com>","Language-Team":"Retainful <support@retainful.com>"}' --allow-root
  cd $current_dir
  echo "Update ini done"
}
copy_pro_folder() {
  if [ -d "$pack_pro_folder" ]; then
    rm -r "$pack_pro_folder"
  fi
  mkdir "$pack_pro_folder"
  mkdir "$plugin_pro_compress_folder"
  move_dir=("i18n" "src" "vendor" "readme.txt" $pro_plugin_name".php")
  # shellcheck disable=SC2068
  for dir in ${move_dir[@]}; do
    cp -r "$current_dir/$dir" "$plugin_pro_compress_folder/$dir"
  done
}

remove_files(){
  cd $plugin_pro_compress_folder
  remove_path="vendor/jaybizzle/crawler-detect/";
  remove_folder=("export.php" ".github" ".php_cs.dist" "composer.json")
  if [ -d "$plugin_pro_compress_folder" ]
  then
    # shellcheck disable=SC2068
    for dir in ${remove_folder[@]}
    do
      rm -r "$plugin_pro_compress_folder/$remove_path$dir"
    done
  fi
  cd $current_dir
}

zip_pro_folder(){
  cd $pack_pro_folder
  pack_folder_name="retainful-wordpress"
  rm "$pack_folder_name".zip
  zip -r "$pack_folder_name".zip $pro_plugin_name -q
  zip -d "$pack_folder_name".zip __MACOSX/\*
  zip -d "$pack_folder_name".zip \*/.DS_Store
}

echo "Composer Run:"
composer_run
echo "Update ini"
update_ini_file
echo "Copy Folder:"
copy_pro_folder
remove_files
echo "Zip Folder:"
zip_pro_folder
echo "End"