#!/usr/bin/env bash
VERSION=$(cat ./package.json | jq -r '.version')

clean() {
  rm -rf ./releases
}

build() {
  echo "[+] Starting combingFiles"

  composer install --no-dev --no-ansi --no-cache --no-interaction
  npm run build

  mkdir -p releases/wp-thrivedesk

  cp -vr ./assets ./releases/wp-thrivedesk
  cp -vr ./database ./releases/wp-thrivedesk
  cp -vr ./Hooks ./releases/wp-thrivedesk
  cp -vr ./includes ./releases/wp-thrivedesk
  cp -vr ./resources ./releases/wp-thrivedesk
  cp -vr ./src ./releases/wp-thrivedesk
  cp -vr ./vendor ./releases/wp-thrivedesk
  cp -vr ./readme.txt ./releases/wp-thrivedesk
  cp -vr ./thrivedesk.php ./releases/wp-thrivedesk

  echo "[+] Finished combingFiles"
}

zipFolder(){
  echo "[+] Creating zip"
  current_dir=$(pwd)
  echo "${current_dir}"
  cd "${current_dir}/releases" && zip -r "${current_dir}/releases/wp-thrivedesk_v${VERSION}.zip" .
}

buildThePackage() {
  clean
  build
  zipFolder
}

echo "[+] WPThriveDesk Pro packaging..."

buildThePackage

echo "[+] Done"
