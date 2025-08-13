#!/usr/bin/env bash
VERSION=$(cat ./package.json | jq -r '.version')

clean() {
  rm -rf ./releases
}

build() {
  echo "[+] Starting combingFiles"

  composer install --no-dev --no-ansi --no-cache --no-interaction
  npm run build

  mkdir -p releases/thrivedesk

  cp -vr ./assets ./releases/thrivedesk
  cp -vr ./database ./releases/thrivedesk
  cp -vr ./Hooks ./releases/thrivedesk
  cp -vr ./includes ./releases/thrivedesk
  cp -vr ./resources ./releases/thrivedesk
  cp -vr ./src ./releases/thrivedesk
  cp -vr ./vendor ./releases/thrivedesk
  cp -vr ./readme.txt ./releases/thrivedesk
  cp -vr ./changelog.txt ./releases/thrivedesk
  cp -vr ./composer.json ./releases/thrivedesk
  cp -vr ./thrivedesk.php ./releases/thrivedesk

  echo "[+] Finished combingFiles"
}

zipFolder(){
  echo "[+] Creating zip"
  current_dir=$(pwd)
  echo "${current_dir}"
  cd "${current_dir}/releases" && zip -r "${current_dir}/releases/thrivedesk_v${VERSION}.zip" .
}

buildThePackage() {
  clean
  build
  zipFolder
}

echo "[+] WPThriveDesk Pro packaging..."

buildThePackage

echo "[+] Done"
