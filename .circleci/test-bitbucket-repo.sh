#!/bin/bash

set -e

TERMINUS_SITE=build-tools-$CIRCLE_BUILD_NUM

# If we are on the master branch
if [[ $CIRCLE_BRANCH == "master" ]]
then
    PR_BRANCH="dev-master"
else
    # If this is a pull request use the PR number
    if [[ -z "$CIRCLE_PULL_REQUEST" ]]
    then
        # Stash PR number
        PR_NUMBER=${CIRCLE_PULL_REQUEST##*/}

        # Multidev name is the pull request number
        PR_BRANCH="pr-$PR_NUMBER"
    else
        # Otherwise use the build number
        PR_BRANCH="dev-$CIRCLE_BUILD_NUM"
    fi
fi

SOURCE_COMPOSER_PROJECT="$1"

# If we are on the 1.x branch set the build tools version to 1.x
if [[ $CIRCLE_BRANCH == "1.x" ]]
then
    BUILD_TOOLS_VERSION="${CIRCLE_BRANCH}#${CIRCLE_SHA1}"
# Otherwise use the current branch
else
    # If on root repo use the current branch
    if [[ $CIRCLE_PROJECT_USERNAME == "pantheon-systems" ]]; then
        BUILD_TOOLS_VERSION="dev-${CIRCLE_BRANCH}#${CIRCLE_SHA1}"
    # Otherwise use the dev tip from the pantheon-systems repo
    else
        BUILD_TOOLS_VERSION="dev-master"
    fi
fi

TARGET_REPO=$BITBUCKET_USER/$TERMINUS_SITE
CLONE_URL="https://$BITBUCKET_USER@bitbucket.org/${TARGET_REPO}.git"

terminus build:project:create -n "$SOURCE_COMPOSER_PROJECT" "$TERMINUS_SITE" --git=bitbucket --team="$TERMINUS_ORG" --email="$GIT_EMAIL" --env="BUILD_TOOLS_VERSION=$BUILD_TOOLS_VERSION"
# Confirm that the Pantheon site was created
terminus site:info "$TERMINUS_SITE"
# Confirm that the Github or Bitbucket project was created
git clone "$CLONE_URL" "$TARGET_REPO_WORKING_COPY"
