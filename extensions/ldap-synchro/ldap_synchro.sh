#!/bin/bash
# LDAP to iTop Synchro Automation Script
# Exports users from AD and imports them into iTop via synchro
#
# Person (contact) data source id  : PERSON_DATA_SOURCE_ID (default 4)
# User account data source id      : USER_DATA_SOURCE_ID   (default 5)

ITOP_DIR="/var/www/html/itop_new"
LOG_DIR="$ITOP_DIR/log"
LOG_FILE="$LOG_DIR/ldap_synchro.log"
AUTH_USER="admin"
AUTH_PWD="admin123"

PERSON_DATA_SOURCE_ID=4
USER_DATA_SOURCE_ID=5

mkdir -p "$LOG_DIR"
touch "$LOG_FILE"
chmod 666 "$LOG_FILE" 2>/dev/null

echo "=== LDAP Synchro started: $(date) ===" >> "$LOG_FILE"

PERSON_CSV="/tmp/ad_users_$(date +%Y%m%d_%H%M%S).csv"
USER_CSV="/tmp/ad_users_accounts_$(date +%Y%m%d_%H%M%S).csv"

# Step 1: Export from AD to both CSVs
php "$ITOP_DIR/extensions/ldap-synchro/ad_export.php" "$PERSON_CSV" "$USER_CSV" >> "$LOG_FILE" 2>&1
if [ $? -ne 0 ]; then
    echo "ERROR: LDAP export failed" >> "$LOG_FILE"
    exit 1
fi

PERSON_COUNT=$(tail -n +2 "$PERSON_CSV" | wc -l)
echo "Exported $PERSON_COUNT users (Person CSV) from AD" >> "$LOG_FILE"

# Step 2: Import Person CSV into iTop synchro and synchronize
php "$ITOP_DIR/synchro/synchro_import.php" \
    --auth_user="$AUTH_USER" \
    --auth_pwd="$AUTH_PWD" \
    --data_source_id="$PERSON_DATA_SOURCE_ID" \
    --csvfile="$PERSON_CSV" \
    --separator=";" \
    --charset=UTF-8 \
    --synchronize=1 \
    --no_stop_on_import_error \
    >> "$LOG_FILE" 2>&1
if [ $? -ne 0 ]; then
    echo "ERROR: Person synchro import failed" >> "$LOG_FILE"
fi

# Step 3: Import User accounts CSV into user synchro datasource (if configured)
if [ -f "$USER_CSV" ]; then
    USER_COUNT=$(tail -n +2 "$USER_CSV" | wc -l)
    echo "Exported $USER_COUNT user accounts (User CSV) from AD" >> "$LOG_FILE"
    php "$ITOP_DIR/synchro/synchro_import.php" \
        --auth_user="$AUTH_USER" \
        --auth_pwd="$AUTH_PWD" \
        --data_source_id="$USER_DATA_SOURCE_ID" \
        --csvfile="$USER_CSV" \
        --separator=";" \
        --charset=UTF-8 \
        --synchronize=1 \
        --no_stop_on_import_error \
        >> "$LOG_FILE" 2>&1
    if [ $? -ne 0 ]; then
        echo "ERROR: User account synchro import failed (is data source $USER_DATA_SOURCE_ID configured?)" >> "$LOG_FILE"
    fi
fi

# Step 4: Cleanup old CSV files (keep last 7 days)
find /tmp -name "ad_users_*.csv" -mtime +7 -delete 2>/dev/null

echo "=== LDAP Synchro finished: $(date) ===" >> "$LOG_FILE"
echo "" >> "$LOG_FILE"
