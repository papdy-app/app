#!/bin/bash -e

scriptName="${0##*/}"

usage()
{
cat >&2 << EOF

usage: ${scriptName} [major|minor|patch] [message] [--yes]

Raises the version in config/app.php and build/linux/debian/DEBIAN/control, builds the Phar, the binaries and the
Debian package with "composer build" in the running ads container (ads rise), commits everything, tags the version,
pushes the current branch and creates a GitHub release.

ARGUMENTS:
  major|minor|patch  Part of the version to increase (default: patch)
  message            Commit message and release title
                     (required when there are uncommitted changes,
                     otherwise defaults to the subject of the last commit)

OPTIONS:
  --yes              Do not ask for confirmation

Example: ${scriptName} "Fix minutes in deployment directory name"
Example: ${scriptName} minor "Added plain OpenSSH implementation"
EOF
}

containerName="papdy_app"
containerPath="/var/www/papdy"

bump="patch"
message=
confirmed=0

while [[ "$#" -gt 0 ]]; do
  case "${1}" in
    -h|--help|-\?) usage; exit 0;;
    --yes|-y) confirmed=1;;
    major|minor|patch) bump="${1}";;
    -*) >&2 echo "Unknown option: ${1}"; usage; exit 1;;
    *)
      if [[ -n "${message}" ]]; then
        >&2 echo "Message already defined, quote it if it contains spaces"
        usage
        exit 1
      fi
      message="${1}"
      ;;
  esac
  shift
done

for requiredCommand in git gh docker; do
  if [[ -z "$(command -v "${requiredCommand}")" ]]; then
    >&2 echo "Please install required package: ${requiredCommand}"
    exit 1
  fi
done

releasePath=$(cd -P "$(dirname "${BASH_SOURCE[0]}")" && pwd)
cd "${releasePath}"

if [[ "$(docker inspect -f '{{.State.Running}}' "${containerName}" 2>/dev/null)" != "true" ]]; then
  >&2 echo "Container ${containerName} is not running, please create it with: ads rise"
  exit 1
fi

branch=$(git rev-parse --abbrev-ref HEAD)
if [[ "${branch}" != "master" ]] && [[ ! "${branch}" =~ ^version/ ]]; then
  >&2 echo "Releases are only created from master or a version/* branch, current branch: ${branch}"
  exit 1
fi

echo "Fetching remote state"
git fetch --quiet --tags origin

if git rev-parse --quiet --verify "refs/remotes/origin/${branch}" >/dev/null && \
  ! git merge-base --is-ancestor "origin/${branch}" HEAD; then
  >&2 echo "Local ${branch} is behind or diverged from origin/${branch}, please pull first"
  exit 1
fi

# Only tags of the current branch, so that a version branch continues its own line
lastVersion=$(git tag -l --merged HEAD | grep -E '^[0-9]+\.[0-9]+\.[0-9]+$' | sort -V | tail -n 1)
if [[ -z "${lastVersion}" ]]; then
  lastVersion="0.0.0"
fi

readarray -d . -t versionParts < <(printf '%s' "${lastVersion}")
major="${versionParts[0]}"
minor="${versionParts[1]}"
patch="${versionParts[2]}"

case "${bump}" in
  major) version="$((major+1)).0.0";;
  minor) version="${major}.$((minor+1)).0";;
  patch) version="${major}.${minor}.$((patch+1))";;
esac

if git rev-parse --quiet --verify "refs/tags/${version}" >/dev/null; then
  >&2 echo "Tag already exists: ${version}"
  exit 1
fi

changes=$(git status --porcelain)

if [[ -n "${changes}" ]]; then
  if [[ -z "${message}" ]]; then
    >&2 echo "There are uncommitted changes, please specify a message"
    usage
    exit 1
  fi
else
  if [[ "${lastVersion}" != "0.0.0" ]] && [[ $(git rev-list -n 1 "${lastVersion}") == $(git rev-parse HEAD) ]]; then
    >&2 echo "Nothing to release, HEAD is already tagged as: ${lastVersion}"
    exit 1
  fi
  if [[ -z "${message}" ]]; then
    message=$(git log -1 --format=%s)
  fi
fi

echo ""
echo "Version: ${lastVersion} -> ${version}" | sed $'s,.*,\e[1;37m&\e[m,'
echo "Branch: ${branch}"
echo "Message: ${message}"
if [[ -n "${changes}" ]]; then
  echo "Changes to commit:"
  echo "${changes}"
fi
if [[ "${lastVersion}" != "0.0.0" ]] && [[ $(git rev-list --count "${lastVersion}..HEAD") -gt 0 ]]; then
  echo "Commits to release:"
  git log --oneline "${lastVersion}..HEAD"
fi
echo ""

if [[ "${confirmed}" == 0 ]]; then
  read -r -p "Create release ${version}? [y/N] " answer < /dev/tty
  if [[ "${answer}" != "y" ]] && [[ "${answer}" != "Y" ]]; then
    echo "Release aborted"
    exit 1
  fi
fi

echo "Setting version: ${version}"
sed -i -E "s/('version' => ')[^']*(')/\1${version}\2/" config/app.php
sed -i -E "s/^Version: .*/Version: ${version}/" build/linux/debian/DEBIAN/control

echo "Building in container: ${containerName}"
docker exec -u "$(id -u):$(id -g)" -w "${containerPath}" "${containerName}" composer build

builtVersion=$(docker exec -w "${containerPath}" "${containerName}" php build/papdy.phar --version)
if [[ "${builtVersion}" != "Papdy ${version}" ]]; then
  >&2 echo "Built Phar reports: ${builtVersion}, expected: Papdy ${version}"
  exit 1
fi

git add -A
git commit --quiet -m "${message}"
echo "Committed changes: $(git log -1 --format='%h %s')"

git tag "${version}"
echo "Created tag: ${version}"

git push --quiet origin "${branch}"
git push --quiet origin "${version}"
echo "Pushed ${branch} and tag: ${version}"

if ! gh release create "${version}" --verify-tag --title "${message}" --notes ""; then
  >&2 echo "Could not create release, retry with:"
  >&2 echo "gh release create ${version} --verify-tag --title \"${message}\" --notes \"\""
  exit 1
fi

echo "Successfully created release: ${version}" | sed $'s,.*,\e[0;32m&\e[m,'
