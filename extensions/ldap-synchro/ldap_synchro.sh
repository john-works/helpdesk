#!/bin/bash
# LDAP to iTop Synchro Automation Script
# Exports users from AD and imports them into iTop via synchro

ITOP_DIR="/var/www/html/helpdesk"
LOG_DIR="$ITOP_DIR/log"
CSV_FILE="/tmp/ad_users_$(date +%Y%m%d_%H%M%S).csv"
LOG_FILE="$LOG_DIR/ldap_synchro.log"
DATA_SOURCE_ID=4
AUTH_USER="admin"
AUTH_PWD="admin123"

mkdir -p "$LOG_DIR"
touch "$LOG_FILE"
chmod 666 "$LOG_FILE" 2>/dev/null

echo "=== LDAP Synchro started: $(date) ===" >> "$LOG_FILE"

# Step 1: Export from AD to CSV
php "$ITOP_DIR/extensions/ldap-synchro/ad_export.php" "$CSV_FILE" >> "$LOG_FILE" 2>&1
if [ $? -ne 0 ]; then
    echo "ERROR: LDAP export failed" >> "$LOG_FILE"
    exit 1
fi

CSV_COUNT=$(tail -n +2 "$CSV_FILE" | wc -l)
echo "Exported $CSV_COUNT users from AD" >> "$LOG_FILE"

# Step 2: Import CSV into iTop synchro and synchronize
php "$ITOP_DIR/synchro/synchro_import.php" \
    --auth_user="$AUTH_USER" \
    --auth_pwd="$AUTH_PWD" \
    --data_source_id="$DATA_SOURCE_ID" \
    --csvfile="$CSV_FILE" \
    --separator=";" \
    --charset=UTF-8 \
    --synchronize=1 \
    --no_stop_on_import_error \
    >> "$LOG_FILE" 2>&1

SYNCHRO_EXIT=$?
if [ $SYNCHRO_EXIT -ne 0 ]; then
    echo "ERROR: Synchro import failed with exit code $SYNCHRO_EXIT" >> "$LOG_FILE"
else
    echo "Synchro import completed successfully" >> "$LOG_FILE"
fi

# Step 3: Cleanup old CSV files (keep last 7 days)
find /tmp -name "ad_users_*.csv" -mtime +7 -delete 2>/dev/null

echo "=== LDAP Synchro finished: $(date) ===" >> "$LOG_FILE"
echo "" >> "$LOG_FILE"
