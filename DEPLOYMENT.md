# 🚀 Deployment Guide

## Current Setup

Your HearMed Portal automatically deploys to SiteGround using GitHub Actions.

### ✅ What's Already Configured

- **Repository**: https://github.com/Raurik/Portal.Hearmedpayments
- **Target Server**: ssh.hearmedpayments.net (port 18765)
- **Target Path**: `~/www/portal.hearmedpayments.net/public_html/wp-content/plugins/hearmed-calendar`
- **Deployment Method**: Git pull via SSH
- **Trigger**: Automatic on push to `main` branch

## 📦 How Auto-Deployment Works

1. You make changes to your code locally or in Codespaces
2. Commit your changes: `git add . && git commit -m "Your message"`
3. Push to GitHub: `git push origin main`
4. **GitHub Actions automatically triggers** within seconds
5. The workflow SSH's into your SiteGround server
6. Runs `git pull origin main` in the plugin directory
7. Your live site is updated! ✨

**Total deployment time**: ~11-20 seconds

## 🔧 Quick Commands

### To deploy changes:
```bash
git add .
git commit -m "Description of your changes"
git push origin main
```

### To check deployment status:
```bash
gh run list --limit 5
```

### To view latest deployment details:
```bash
gh run view $(gh run list --limit 1 --json databaseId --jq '.[0].databaseId')
```

### To watch deployment in real-time:
```bash
gh run watch
```

## 🔐 Required GitHub Secrets

These are already configured in your repository (Settings → Secrets and variables → Actions):

- `SG_SSH_KEY`: Private SSH key for authenticating to SiteGround
- `SG_SSH_PASSPHRASE`: Passphrase for the SSH key

## 📋 Deployment Workflow File

Location: `.github/workflows/deploy.yml`

The workflow:
- Triggers on push to `main` branch
- Uses `appleboy/ssh-action` to connect to SiteGround
- Executes `git pull origin main` on the server

## ⚠️ Important Notes

1. **Direct Server Changes**: Avoid making changes directly on the SiteGround server, as they will be overwritten on the next deployment
2. **Branch Protection**: All changes should be pushed to the `main` branch
3. **Testing**: Test changes locally or in a development environment before pushing to `main`
4. **Rollback**: If needed, you can rollback by reverting the commit and pushing again

## 🐛 Troubleshooting

### If deployment fails:

1. Check the workflow run status:
   ```bash
   gh run list
   ```

2. View the detailed error log:
   ```bash
   gh run view [RUN_ID] --log
   ```

3. Common issues:
   - SSH key expired or invalid
   - Merge conflicts on server
   - File permission issues
   - Network connectivity problems

### To manually sync if needed:

SSH into your SiteGround server and run:
```bash
cd ~/www/portal.hearmedpayments.net/public_html/wp-content/plugins/hearmed-calendar
git status
git pull origin main
```

## 📊 Monitoring

- View deployment history: https://github.com/Raurik/Portal.Hearmedpayments/actions
- All deployments are logged with timestamps and status
- Failed deployments will show ❌ and successful ones show ✓

## 🎯 Next Steps After Deployment

After code is deployed to SiteGround, remember to:

1. Clear WordPress cache (if using caching plugin)
2. Test the affected features on the live site
3. Monitor error logs: `/wp-content/debug.log`
4. Check PHP error logs in SiteGround cPanel

## 🔄 Updating the Deployment Workflow

To modify the deployment process, edit `.github/workflows/deploy.yml` and push the changes. The new workflow will be used for subsequent deployments.
