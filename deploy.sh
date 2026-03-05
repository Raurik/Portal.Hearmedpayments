#!/bin/bash
# Deploy to SiteGround via GitHub Actions
# Usage: ./deploy.sh [commit message]
# This works around the Codespace token limitation that prevents push-triggered Actions

set -e
cd "$(dirname "$0")"

MSG="${1:-Deploy from Codespace}"

# Stage and commit any changes
if [[ -n $(git status --porcelain) ]]; then
    git add -A
    git commit -m "$MSG"
fi

# Push normally (code goes to GitHub but won't trigger Actions)
git push origin main

# Now trigger the deploy via GitHub API (empty commit + ref update)
HEAD_SHA=$(gh api /repos/Raurik/Portal.Hearmedpayments/git/ref/heads/main --jq '.object.sha')
TREE_SHA=$(gh api /repos/Raurik/Portal.Hearmedpayments/git/commits/"$HEAD_SHA" --jq '.tree.sha')
NEW_SHA=$(gh api /repos/Raurik/Portal.Hearmedpayments/git/commits \
  -f message="$MSG" \
  -f "tree=$TREE_SHA" \
  -f "parents[]=$HEAD_SHA" \
  --jq '.sha')
gh api /repos/Raurik/Portal.Hearmedpayments/git/refs/heads/main \
  -X PATCH -f sha="$NEW_SHA" --jq '.object.sha' > /dev/null

echo "✅ Pushed and deploy triggered! Check: https://github.com/Raurik/Portal.Hearmedpayments/actions"

# Sync local
git pull origin main --quiet
