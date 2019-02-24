#!/bin/bash

set -e

BUILD_TOOLS_VERSION="dev-master"
if [[ -n "$CIRCLE_BRANCH" ]]; then
    BUILD_TOOLS_VERSION="dev-${CIRCLE_BRANCH}#${CIRCLE_SHA1}"
fi

TERMINUS_SITE=build-tools-$CIRCLE_BUILD_NUM
SOURCE_COMPOSER_PROJECT="$1"
TARGET_REPO=$BITBUCKET_USER/$TERMINUS_SITE
CLONE_URL="https://$BITBUCKET_USER@bitbucket.org/${TARGET_REPO}.git"

# Build a test project on bitbucket
terminus build:project:create -n "$SOURCE_COMPOSER_PROJECT" "$TERMINUS_SITE" --git=bitbucket --team="$TERMINUS_ORG" --email="$GIT_EMAIL" --env="BUILD_TOOLS_VERSION=$BUILD_TOOLS_VERSION"
# Confirm that the Pantheon site was created
terminus site:info "$TERMINUS_SITE"
# Confirm that the Github or Bitbucket project was created
git clone "$CLONE_URL" "$TARGET_REPO_WORKING_COPY"