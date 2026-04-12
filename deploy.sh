#!/bin/bash

# ================================================================= #
# R-Active LMS — One-Click Deploy Script                           #
# Usage: ./deploy.sh                                               #
# ================================================================= #

echo "🚀 Starting Deployment Update..."

# 1. Enter the project directory
cd /var/www/ractive || { echo "❌ Error: Directory /var/www/ractive not found"; exit 1; }

# 2. Fix permissions so ec2-user can pull from Git
echo "🔄 Preparing Git permissions..."
sudo chown -R ec2-user:ec2-user /var/www/ractive/.git
sudo chown -R ec2-user:ec2-user /var/www/ractive

# 3. Pull the latest code from GitHub
echo "📥 Pulling latest changes from GitHub..."
git pull origin main

# 4. Set professional permissions for Moodle
# Apache owner, 755 for directories, 644 for files
echo "🔐 Setting secure permissions for Apache..."
sudo chown -R apache:apache /var/www/ractive
sudo find /var/www/ractive -type d -exec chmod 755 {} \;
sudo find /var/www/ractive -type f -exec chmod 644 {} \;

# Ensure moodledata is also correct
sudo chown -R apache:apache /var/moodledata
sudo chmod -R 770 /var/moodledata

# 5. Run Moodle Database Upgrade
echo "🛠️ Running Moodle Database Upgrade..."
sudo -u apache php admin/cli/upgrade.php --non-interactive

# 6. Purge Moodle Caches (Ensures new CSS/Plugins show up)
echo "🧹 Purging Moodle Caches..."
sudo -u apache php admin/cli/purge_caches.php

echo "✅ Deployment Complete! Your site is updated."
