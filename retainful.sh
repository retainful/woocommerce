echo "Retainful Pro pack"
current_dir="$PWD"
composer_run() {
  # shellcheck disable=SC2164
  cd "$current_dir"
  composer install --no-dev
  composer update --no-dev
  cd ..
  echo "Compress Done"
  cd $current_dir
}

update_ini_file() {
  cd "$current_dir"
  wp i18n make-pot . "i18n/languages/retainful-next-order-coupon-for-woocommerce.pot" --slug="retainful-next-order-coupon-for-woocommerce" --domain="retainful-next-order-coupon-for-woocommerce" --include=retainful-next-order-coupon-for-woocommerce.php,/App/ --headers='{"Last-Translator":"retainful <support@retainful.com>","Language-Team":"retainful <support@retainful.com>"}'
  cd "$current_dir"
  echo "Update ini done"
}

copy_folder() {
  cd $current_dir
  cd ..
  pack_folder=$PWD"/compressed_pack"
  compress_plugin_folder=$pack_folder"/retainful-next-order-coupon-for-woocommerce"
  if [ -d "$pack_folder" ]; then
    rm -r "$pack_folder"
  fi
  mkdir "$pack_folder"
  mkdir "$compress_plugin_folder"
  move_dir=("App" "assets" "i18n" "vendor" "readme.txt" "retainful-next-order-coupon-for-woocommerce.php")
  # shellcheck disable=SC2068
  for dir in ${move_dir[@]}; do
    cp -r "$current_dir/$dir" "$compress_plugin_folder/$dir"
  done
  cd "$current_dir"
}

zip_folder() {
  cd "$current_dir"
  cd ..
  pack_compress_folder=$PWD"/compressed_pack"
  cd "$pack_compress_folder"
  pack_name="retainful-next-order-coupon-for-woocommerce"
  zip_name="retainful-wordpress"
  rm "$zip_name".zip
  zip -r "$zip_name".zip $pack_name -q
  zip -d "$zip_name".zip __MACOSX/\*
  zip -d "$zip_name".zip \*/.DS_Store
}
echo "Composer Run:"
composer_run
echo "Update ini"
update_ini_file
echo "Copy Folder:"
copy_folder
echo "Zip Folder:"
zip_folder
echo "End"