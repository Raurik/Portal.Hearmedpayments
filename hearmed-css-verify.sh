#!/bin/bash
# ═══════════════════════════════════════════════════════════════
# HearMed CSS Migration Verification Script
# Run from plugin root: /srv/htdocs/wp-content/plugins/hearmed-calendar/
# ═══════════════════════════════════════════════════════════════

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No colour
BOLD='\033[1m'

PASS=0
FAIL=0
WARN=0

check() {
  local label="$1"
  local pattern="$2"
  local scope="$3" # files to search
  local count
  count=$(grep -rl "$pattern" $scope --include="*.php" 2>/dev/null | wc -l)
  local instances
  instances=$(grep -r "$pattern" $scope --include="*.php" 2>/dev/null | wc -l)
  if [ "$instances" -eq 0 ]; then
    echo -e "  ${GREEN}✓${NC} $label — ${GREEN}done${NC}"
    ((PASS++))
  else
    echo -e "  ${RED}✕${NC} $label — ${RED}$instances instances in $count files${NC}"
    grep -rn "$pattern" $scope --include="*.php" 2>/dev/null | head -5
    echo ""
    ((FAIL++))
  fi
}

check_warn() {
  local label="$1"
  local pattern="$2"
  local scope="$3"
  local count
  count=$(grep -r "$pattern" $scope --include="*.php" 2>/dev/null | wc -l)
  if [ "$count" -eq 0 ]; then
    echo -e "  ${GREEN}✓${NC} $label — ${GREEN}none found${NC}"
    ((PASS++))
  else
    echo -e "  ${YELLOW}⚠${NC} $label — ${YELLOW}$count instances (check if dynamic/legitimate)${NC}"
    grep -rn "$pattern" $scope --include="*.php" 2>/dev/null | head -5
    echo ""
    ((WARN++))
  fi
}

echo ""
echo -e "${BOLD}${CYAN}═══════════════════════════════════════════════════${NC}"
echo -e "${BOLD}${CYAN}  HearMed CSS Migration — Full Verification Report${NC}"
echo -e "${BOLD}${CYAN}═══════════════════════════════════════════════════${NC}"
echo ""

# ─────────────────────────────────────────
echo -e "${BOLD}1. MODULE PREFIX CLASSES (should all be hm-)${NC}"
# ─────────────────────────────────────────
check "hmf-btn (fitting buttons)" 'hmf-btn' "modules/"
check "hmf-stat / hmf-stats" 'hmf-stat' "modules/"
check "hmf-tbl (fitting tables)" 'hmf-tbl' "modules/"
check "hmf-empty (fitting empty)" 'hmf-empty' "modules/"
check "hmf-modal (fitting modals)" 'hmf-modal' "modules/"
check "hmf-form-group" 'hmf-form-group' "modules/"
check "hmf-warning / hmf-success" 'hmf-warning\|hmf-success' "modules/"
check "hmos-stat (order-status stats)" 'hmos-stat' "modules/"
check "hmos-tbl (order-status tables)" 'hmos-tbl' "modules/"
check "hmos-btn (order-status buttons)" 'hmos-btn' "modules/"
check "hmos-empty (order-status empty)" 'hmos-empty' "modules/"
check "hma-card (approvals cards)" 'hma-card' "modules/"
check "hma-tbl (approvals tables)" 'hma-tbl' "modules/"
check "hma-empty (approvals empty)" 'hma-empty' "modules/"
check "ft-ov-tab (form template tabs)" 'ft-ov-tab' "admin/"
check "ft-icon-btn" 'ft-icon-btn' "admin/"
check "ft-primary-btn" 'ft-primary-btn' "admin/"
check "ft-back" 'ft-back' "admin/"
check "ft-badge" 'ft-badge' "admin/"
check "ft-form-group" 'ft-form-group' "admin/"
echo ""

# ─────────────────────────────────────────
echo -e "${BOLD}2. OLD BUTTON CLASSES${NC}"
# ─────────────────────────────────────────
check "hm-btn-teal (→ hm-btn--primary)" 'hm-btn-teal' "modules/ admin/"
check "hm-btn-red (→ hm-btn--danger)" 'hm-btn-red' "modules/ admin/"
check "hm-btn-add (→ hm-btn--add)" 'hm-btn-add' "modules/ admin/"
echo ""

# ─────────────────────────────────────────
echo -e "${BOLD}3. OLD BADGE CLASSES${NC}"
# ─────────────────────────────────────────
check "hm-badge-green (no --)" 'hm-badge-green' "modules/ admin/"
check "hm-badge-red" 'hm-badge-red' "modules/ admin/"
check "hm-badge-yellow" 'hm-badge-yellow' "modules/ admin/"
check "hm-badge-blue" 'hm-badge-blue' "modules/ admin/"
check "hm-badge-grey / hm-badge-gray" 'hm-badge-grey\|hm-badge-gray' "modules/ admin/"
check "hm-badge-orange" 'hm-badge-orange' "modules/ admin/"
echo ""

# ─────────────────────────────────────────
echo -e "${BOLD}4. OLD MODAL CLASSES${NC}"
# ─────────────────────────────────────────
check "hm-modal__backdrop (BEM)" 'hm-modal__backdrop' "modules/"
check "hm-modal__box" 'hm-modal__box' "modules/"
check "hm-modal__header" 'hm-modal__header' "modules/"
check "hm-modal__close" 'hm-modal__close' "modules/"
check "hm-modal-x (→ hm-close)" 'hm-modal-x' "modules/ admin/"
check "hm-chat-modal-close" 'hm-chat-modal-close' "modules/"
echo ""

# ─────────────────────────────────────────
echo -e "${BOLD}5. OLD FORM GROUP CLASSES${NC}"
# ─────────────────────────────────────────
check "hm-form__field (BEM)" 'hm-form__field' "modules/ admin/"
check "hm-stg-field (settings)" 'hm-stg-field' "modules/ admin/"
echo ""

# ─────────────────────────────────────────
echo -e "${BOLD}6. OLD TITLE CLASSES${NC}"
# ─────────────────────────────────────────
check "hm-sc-title (admin console)" 'hm-sc-title' "modules/ admin/"
check "ft-page-title" 'ft-page-title' "admin/"
check "ft-page-sub" 'ft-page-sub' "admin/"
echo ""

# ─────────────────────────────────────────
echo -e "${BOLD}7. CLOSE BUTTON CHARACTERS${NC}"
# ─────────────────────────────────────────
TICK_X=$(grep -rn '✕' modules/ admin/ --include="*.php" 2>/dev/null | grep -v "\.css" | wc -l)
if [ "$TICK_X" -eq 0 ]; then
  echo -e "  ${GREEN}✓${NC} ✕ character (should be ×) — ${GREEN}none found${NC}"
  ((PASS++))
else
  echo -e "  ${RED}✕${NC} ✕ character still used — ${RED}$TICK_X instances (should be × / &times;)${NC}"
  grep -rn '✕' modules/ admin/ --include="*.php" 2>/dev/null | head -5
  echo ""
  ((FAIL++))
fi
echo ""

# ─────────────────────────────────────────
echo -e "${BOLD}8. BACK BUTTONS${NC}"
# ─────────────────────────────────────────
check "hm-back-btn (→ hm-back)" 'hm-back-btn' "modules/ admin/"
LARR_BACK=$(grep -rn '&larr; Back\|← Back' admin/ --include="*.php" 2>/dev/null | grep 'hm-btn' | wc -l)
if [ "$LARR_BACK" -eq 0 ]; then
  echo -e "  ${GREEN}✓${NC} Admin back links using hm-btn — ${GREEN}all migrated to hm-back${NC}"
  ((PASS++))
else
  echo -e "  ${RED}✕${NC} Admin back links still using hm-btn — ${RED}$LARR_BACK instances${NC}"
  grep -rn '&larr; Back\|← Back' admin/ --include="*.php" 2>/dev/null | grep 'hm-btn' | head -5
  echo ""
  ((FAIL++))
fi
echo ""

# ─────────────────────────────────────────
echo -e "${BOLD}9. EMPTY STATES${NC}"
# ─────────────────────────────────────────
check "hm-notif-empty" 'hm-notif-empty' "modules/"
check "hm-table__empty" 'hm-table__empty' "modules/"
check "hm-forms-empty" 'hm-forms-empty' "modules/"
check "hm-order-items__empty" 'hm-order-items__empty' "modules/"
echo ""

# ─────────────────────────────────────────
echo -e "${BOLD}10. STAT CARDS${NC}"
# ─────────────────────────────────────────
check "hm-day-tile (notifications)" 'hm-day-tile' "modules/"
check "hm-notif-day-tiles" 'hm-notif-day-tiles' "modules/"
check "hm-cn-stat (credit notes)" 'hm-cn-stat' "modules/"
check "hm-stat-card (old name)" 'hm-stat-card' "modules/"
echo ""

# ─────────────────────────────────────────
echo -e "${BOLD}11. TAB BARS${NC}"
# ─────────────────────────────────────────
check '"hm-tabs" (→ hm-tab-bar)' '"hm-tabs"' "modules/"
echo ""

# ─────────────────────────────────────────
echo -e "${BOLD}12. TOGGLE SWITCHES — HTML STRUCTURE${NC}"
# ─────────────────────────────────────────
check "hm-toggle-label (old class)" 'hm-toggle-label' "modules/ admin/"
TOGGLE_TRACK=$(grep -r 'hm-toggle-track' modules/ admin/ --include="*.php" 2>/dev/null | wc -l)
if [ "$TOGGLE_TRACK" -gt 0 ]; then
  echo -e "  ${GREEN}✓${NC} hm-toggle-track spans added — ${GREEN}$TOGGLE_TRACK instances${NC}"
  ((PASS++))
else
  echo -e "  ${RED}✕${NC} hm-toggle-track spans — ${RED}NOT FOUND (toggles still using old checkbox style)${NC}"
  echo "    Files needing toggle migration:"
  grep -rln 'type="checkbox"' admin/ --include="*.php" 2>/dev/null | grep -v "form-templates\|settings" | head -5
  ((FAIL++))
fi
echo ""

# ─────────────────────────────────────────
echo -e "${BOLD}13. LOADING SPINNERS${NC}"
# ─────────────────────────────────────────
SPINNERS_PHP=$(grep -rn 'hm-spinner' modules/ admin/ --include="*.php" 2>/dev/null | wc -l)
DOTS_PHP=$(grep -rn 'hm-loading-dot' modules/ admin/ --include="*.php" 2>/dev/null | wc -l)
if [ "$SPINNERS_PHP" -eq 0 ]; then
  echo -e "  ${GREEN}✓${NC} Old hm-spinner in PHP — ${GREEN}all removed${NC}"
  ((PASS++))
else
  echo -e "  ${RED}✕${NC} Old hm-spinner still in PHP — ${RED}$SPINNERS_PHP instances${NC}"
  grep -rn 'hm-spinner' modules/ admin/ --include="*.php" 2>/dev/null | head -5
  echo ""
  ((FAIL++))
fi
echo -e "  ${CYAN}ℹ${NC} New hm-loading-dot instances: $DOTS_PHP"
echo ""

# ─────────────────────────────────────────
echo -e "${BOLD}14. NOTICE / ALERT SYSTEM${NC}"
# ─────────────────────────────────────────
check "hm-alert hm-alert-error (old)" 'hm-alert-error\|hm-alert-warning\|hm-alert-success\|hm-alert-info' "modules/ admin/"
check "hm-auth-error (one-off)" 'hm-auth-error' "modules/ admin/"
check "hm-gdpr-notice (one-off)" 'hm-gdpr-notice' "modules/ admin/"
NOTICE_BODY=$(grep -r 'hm-notice-body' modules/ admin/ --include="*.php" 2>/dev/null | wc -l)
NOTICE_RAW=$(grep -r 'class="hm-notice' modules/ admin/ --include="*.php" 2>/dev/null | grep -v 'hm-notice-body\|hm-notice-icon\|hm-notice--' | wc -l)
if [ "$NOTICE_BODY" -gt 0 ]; then
  echo -e "  ${GREEN}✓${NC} Notices using new structure (hm-notice-body) — ${GREEN}$NOTICE_BODY instances${NC}"
  ((PASS++))
else
  echo -e "  ${RED}✕${NC} Notices NOT using new structure — ${RED}no hm-notice-body found${NC}"
  ((FAIL++))
fi
if [ "$NOTICE_RAW" -gt 0 ]; then
  echo -e "  ${YELLOW}⚠${NC} Notices still using OLD structure (no hm-notice-body wrapper) — ${YELLOW}$NOTICE_RAW instances${NC}"
  grep -rn 'class="hm-notice' modules/ admin/ --include="*.php" 2>/dev/null | grep -v 'hm-notice-body\|hm-notice-icon\|hm-notice--' | head -5
  echo ""
  ((WARN++))
fi
echo ""

# ─────────────────────────────────────────
echo -e "${BOLD}15. STATUS DOTS (inline → class)${NC}"
# ─────────────────────────────────────────
INLINE_DOTS=$(grep -rn 'border-radius:50%\|border-radius: 50%' admin/ modules/ --include="*.php" 2>/dev/null | grep 'style=' | grep -v 'calendar\|colour\|color.*<?php' | wc -l)
CLASS_DOTS=$(grep -r 'hm-status-dot' modules/ admin/ --include="*.php" 2>/dev/null | wc -l)
if [ "$CLASS_DOTS" -gt 0 ]; then
  echo -e "  ${GREEN}✓${NC} hm-status-dot class in use — ${GREEN}$CLASS_DOTS instances${NC}"
  ((PASS++))
else
  echo -e "  ${RED}✕${NC} hm-status-dot class — ${RED}NOT FOUND${NC}"
  ((FAIL++))
fi
if [ "$INLINE_DOTS" -gt 0 ]; then
  echo -e "  ${YELLOW}⚠${NC} Inline border-radius:50% dots remaining — ${YELLOW}$INLINE_DOTS (check if dynamic colour)${NC}"
  grep -rn 'border-radius:50%\|border-radius: 50%' admin/ modules/ --include="*.php" 2>/dev/null | grep 'style=' | grep -v 'calendar\|colour\|color.*<?php' | head -5
  echo ""
  ((WARN++))
fi
echo ""

# ─────────────────────────────────────────
echo -e "${BOLD}16. MONEY CLASS${NC}"
# ─────────────────────────────────────────
BARE_MONEY=$(grep -rn 'class="money"' modules/ admin/ --include="*.php" 2>/dev/null | wc -l)
if [ "$BARE_MONEY" -eq 0 ]; then
  echo -e "  ${GREEN}✓${NC} Bare class=\"money\" — ${GREEN}all prefixed to hm-money${NC}"
  ((PASS++))
else
  echo -e "  ${RED}✕${NC} Bare class=\"money\" — ${RED}$BARE_MONEY instances still unprefixed${NC}"
  grep -rn 'class="money"' modules/ admin/ --include="*.php" 2>/dev/null | head -5
  echo ""
  ((FAIL++))
fi
echo ""

# ─────────────────────────────────────────
echo -e "${BOLD}17. INLINE STYLE COUNTS BY FILE${NC}"
# ─────────────────────────────────────────
echo -e "  ${CYAN}Top 15 files with most remaining style=\"\" attributes:${NC}"
echo ""
for f in $(grep -rl 'style="' modules/ admin/ --include="*.php" 2>/dev/null); do
  count=$(grep -o 'style="' "$f" | wc -l)
  echo "    $count  $f"
done | sort -rn | head -15
echo ""

# ─────────────────────────────────────────
echo -e "${BOLD}18. INLINE <style> BLOCKS IN PHP${NC}"
# ─────────────────────────────────────────
STYLE_BLOCKS=$(grep -rl '<style' modules/ admin/ --include="*.php" 2>/dev/null | wc -l)
if [ "$STYLE_BLOCKS" -eq 0 ]; then
  echo -e "  ${GREEN}✓${NC} No <style> blocks in PHP — ${GREEN}all moved to CSS files${NC}"
  ((PASS++))
else
  echo -e "  ${YELLOW}⚠${NC} <style> blocks still in PHP — ${YELLOW}$STYLE_BLOCKS files${NC}"
  grep -rln '<style' modules/ admin/ --include="*.php" 2>/dev/null
  echo ""
  ((WARN++))
fi
echo ""

# ─────────────────────────────────────────
echo -e "${BOLD}19. CSS SCOPING CHECK${NC}"
# ─────────────────────────────────────────
if [ -f "assets/css/hearmed-core.css" ]; then
  UNSCOPED=$(grep -n '^[.#a-zA-Z]' assets/css/hearmed-core.css | grep -v '#hm-app\|:root\|@keyframes\|@media\|/\*\|#hm-bell\| \*/' | wc -l)
  if [ "$UNSCOPED" -eq 0 ]; then
    echo -e "  ${GREEN}✓${NC} hearmed-core.css — ${GREEN}all selectors scoped to #hm-app${NC}"
    ((PASS++))
  else
    echo -e "  ${RED}✕${NC} hearmed-core.css — ${RED}$UNSCOPED unscoped selectors found${NC}"
    grep -n '^[.#a-zA-Z]' assets/css/hearmed-core.css | grep -v '#hm-app\|:root\|@keyframes\|@media\|/\*\|#hm-bell\| \*/' | head -10
    echo ""
    ((FAIL++))
  fi
else
  echo -e "  ${RED}✕${NC} hearmed-core.css not found!"
  ((FAIL++))
fi
echo ""

# ─────────────────────────────────────────
echo -e "${BOLD}20. PAGE HEADER STRUCTURE${NC}"
# ─────────────────────────────────────────
PH_FLEX=$(grep -r 'hm-page-header__actions\|hm-page-header"' modules/ admin/ --include="*.php" 2>/dev/null | wc -l)
PH_INLINE=$(grep -rn 'hm-page-header' modules/ admin/ --include="*.php" 2>/dev/null | grep 'style=' | wc -l)
echo -e "  ${CYAN}ℹ${NC} hm-page-header usage: $PH_FLEX instances"
if [ "$PH_INLINE" -gt 0 ]; then
  echo -e "  ${YELLOW}⚠${NC} Page headers with inline styles — ${YELLOW}$PH_INLINE (should be class-only)${NC}"
  grep -rn 'hm-page-header' modules/ admin/ --include="*.php" 2>/dev/null | grep 'style=' | head -5
  echo ""
  ((WARN++))
fi
echo ""

# ─────────────────────────────────────────
echo -e "${BOLD}21. HARDCODED COLOURS IN PHP${NC}"
# ─────────────────────────────────────────
echo -e "  ${CYAN}Remaining hardcoded hex colours (excluding dynamic <?php):${NC}"
for colour in "#151B33" "#0BB4C4" "#334155" "#64748b" "#e2e8f0" "#f1f5f9" "#94a3b8"; do
  count=$(grep -r "$colour" modules/ admin/ --include="*.php" 2>/dev/null | grep -v '<?php\|//\|/\*\|input.*value\|color.*input' | wc -l)
  if [ "$count" -gt 0 ]; then
    echo -e "    ${YELLOW}$colour${NC}: $count instances"
  fi
done
echo ""

# ─────────────────────────────────────────
echo -e "${BOLD}22. NEW COMPONENTS IN CSS${NC}"
# ─────────────────────────────────────────
if [ -f "assets/css/hearmed-core.css" ]; then
  for cls in "hm-notice" "hm-empty" "hm-loading-dot" "hm-stat" "hm-tab-bar" "hm-toggle-track" "hm-close" "hm-btn--add" "hm-back" "hm-status-dot" "hm-money" "hm-icon-btn" "hm-page-header__actions"; do
    if grep -q "$cls" assets/css/hearmed-core.css 2>/dev/null; then
      echo -e "  ${GREEN}✓${NC} .$cls defined in core.css"
    else
      echo -e "  ${RED}✕${NC} .$cls ${RED}MISSING from core.css${NC}"
      ((FAIL++))
    fi
  done
else
  echo -e "  ${RED}✕${NC} Cannot check — hearmed-core.css not found"
fi
echo ""

# ═══════════════════════════════════════════════════
echo -e "${BOLD}${CYAN}═══════════════════════════════════════════════════${NC}"
echo -e "${BOLD}  SUMMARY${NC}"
echo -e "${BOLD}${CYAN}═══════════════════════════════════════════════════${NC}"
echo ""
echo -e "  ${GREEN}✓ PASSED:  $PASS${NC}"
echo -e "  ${RED}✕ FAILED:  $FAIL${NC}"
echo -e "  ${YELLOW}⚠ WARNINGS: $WARN${NC}"
echo ""

if [ "$FAIL" -eq 0 ]; then
  echo -e "  ${GREEN}${BOLD}All migrations complete! 🎉${NC}"
elif [ "$FAIL" -lt 10 ]; then
  echo -e "  ${YELLOW}${BOLD}Almost there — $FAIL items need attention.${NC}"
else
  echo -e "  ${RED}${BOLD}Significant gaps remain — $FAIL items to fix.${NC}"
fi
echo ""