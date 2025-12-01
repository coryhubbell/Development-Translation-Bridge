#!/bin/bash

# ===================================================================
# DevelopmentTranslation Bridge 3.0 - GitHub Push Automation Script
# ===================================================================
#
# Purpose: Automates the process of pushing your WordPress Bootstrap
#          Claude 3.0 repository to GitHub with proper validation
#          and error handling.
#
# Usage:   ./github-push.sh
#
# Features:
# - Validates git configuration
# - Checks remote repository
# - Verifies branch name
# - Shows preview of changes
# - Confirms before pushing
# - Handles errors gracefully
# - Provides next steps
#
# Author: DevelopmentTranslation Bridge Team
# Version: 1.0.0
# ===================================================================

# Color definitions for beautiful output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
WHITE='\033[1;37m'
NC='\033[0m' # No Color

# Emoji support (works on most terminals)
CHECK="✅"
CROSS="❌"
ROCKET="🚀"
WARNING="⚠️"
INFO="ℹ️"
GEAR="⚙️"
SPARKLES="✨"

# Configuration
REPO_URL="https://github.com/coryhubbell/development-translation-bridge.git"
DEFAULT_BRANCH="main"
COMMIT_MESSAGE="🚀 Release: DevelopmentTranslation Bridge 3.0 - Translation Bridge™

Revolutionary WordPress development framework with Translation Bridge™

Features:
- World's first framework translator (Bootstrap ↔ DIVI ↔ Elementor)
- Claude AI integration for 10x productivity
- Complete component libraries for all frameworks
- Advanced WordPress Loop system
- Plugin conversion toolkit

This release includes:
- Translation Bridge™ core engine
- Universal translator class (9,000+ lines)
- Bootstrap, DIVI, and Elementor mappings
- Claude Code integration
- CLI tools and REST API
- Comprehensive documentation

Version: 3.0.0
Status: Production Ready

🌉 Translation Bridge™ - The framework that changes everything"

# ===================================================================
# Helper Functions
# ===================================================================

# Print a section header
print_header() {
    echo ""
    echo -e "${CYAN}═══════════════════════════════════════════════════════${NC}"
    echo -e "${WHITE}$1${NC}"
    echo -e "${CYAN}═══════════════════════════════════════════════════════${NC}"
    echo ""
}

# Print success message
print_success() {
    echo -e "${GREEN}${CHECK} $1${NC}"
}

# Print error message
print_error() {
    echo -e "${RED}${CROSS} $1${NC}"
}

# Print warning message
print_warning() {
    echo -e "${YELLOW}${WARNING} $1${NC}"
}

# Print info message
print_info() {
    echo -e "${BLUE}${INFO} $1${NC}"
}

# Print step message
print_step() {
    echo -e "${PURPLE}${GEAR} $1${NC}"
}

# Ask for confirmation
confirm() {
    local prompt="$1"
    local default="${2:-n}"

    if [[ $default == "y" ]]; then
        prompt="$prompt [Y/n]: "
    else
        prompt="$prompt [y/N]: "
    fi

    while true; do
        read -p "$(echo -e ${YELLOW}${prompt}${NC})" yn
        yn=${yn:-$default}
        case $yn in
            [Yy]* ) return 0;;
            [Nn]* ) return 1;;
            * ) echo "Please answer yes or no.";;
        esac
    done
}

# ===================================================================
# Validation Functions
# ===================================================================

# Check if git is installed
check_git_installed() {
    print_step "Checking if git is installed..."

    if ! command -v git &> /dev/null; then
        print_error "Git is not installed!"
        echo ""
        echo "Please install git first:"
        echo "  macOS:   brew install git"
        echo "  Ubuntu:  sudo apt-get install git"
        echo "  Windows: https://git-scm.com/download/win"
        echo ""
        exit 1
    fi

    local git_version=$(git --version)
    print_success "Git is installed: $git_version"
}

# Check if we're in a git repository
check_git_repo() {
    print_step "Checking if this is a git repository..."

    if ! git rev-parse --git-dir &> /dev/null; then
        print_error "This is not a git repository!"
        echo ""

        if confirm "Would you like to initialize it now?"; then
            git init
            print_success "Git repository initialized"
        else
            print_error "Cannot continue without git repository"
            exit 1
        fi
    else
        print_success "Git repository found"
    fi
}

# Check git configuration
check_git_config() {
    print_step "Checking git configuration..."

    local user_name=$(git config user.name)
    local user_email=$(git config user.email)

    if [[ -z "$user_name" ]] || [[ -z "$user_email" ]]; then
        print_warning "Git user configuration is incomplete"
        echo ""
        echo "Current configuration:"
        echo "  Name:  ${user_name:-<not set>}"
        echo "  Email: ${user_email:-<not set>}"
        echo ""

        if confirm "Would you like to configure it now?"; then
            read -p "Enter your name: " user_name
            read -p "Enter your email: " user_email

            git config user.name "$user_name"
            git config user.email "$user_email"

            print_success "Git configuration updated"
        else
            print_warning "Continuing without complete git configuration"
        fi
    else
        print_success "Git user: $user_name <$user_email>"
    fi
}

# Check remote repository
check_remote() {
    print_step "Checking remote repository..."

    local current_remote=$(git remote get-url origin 2>/dev/null)

    if [[ -z "$current_remote" ]]; then
        print_warning "No remote repository configured"
        echo ""

        if confirm "Would you like to add the remote now?"; then
            git remote add origin "$REPO_URL"
            print_success "Remote added: $REPO_URL"
        else
            print_error "Cannot push without remote repository"
            exit 1
        fi
    elif [[ "$current_remote" != "$REPO_URL" ]]; then
        print_warning "Remote URL mismatch!"
        echo ""
        echo "Current:  $current_remote"
        echo "Expected: $REPO_URL"
        echo ""

        if confirm "Would you like to update the remote URL?"; then
            git remote set-url origin "$REPO_URL"
            print_success "Remote URL updated"
        else
            print_warning "Continuing with current remote: $current_remote"
        fi
    else
        print_success "Remote configured: $REPO_URL"
    fi
}

# Check current branch
check_branch() {
    print_step "Checking current branch..."

    local current_branch=$(git rev-parse --abbrev-ref HEAD 2>/dev/null)

    if [[ -z "$current_branch" ]]; then
        print_warning "No commits yet, branch will be created on first commit"
        current_branch="$DEFAULT_BRANCH"
    elif [[ "$current_branch" != "$DEFAULT_BRANCH" ]] && [[ "$current_branch" != "master" ]]; then
        print_warning "Current branch: $current_branch"
        echo ""

        if confirm "Would you like to switch to $DEFAULT_BRANCH branch?"; then
            git checkout -b "$DEFAULT_BRANCH" 2>/dev/null || git checkout "$DEFAULT_BRANCH"
            print_success "Switched to $DEFAULT_BRANCH branch"
            current_branch="$DEFAULT_BRANCH"
        fi
    elif [[ "$current_branch" == "master" ]]; then
        print_warning "Current branch is 'master'"
        echo ""

        if confirm "Would you like to rename it to 'main'?"; then
            git branch -m master main
            print_success "Branch renamed to 'main'"
            current_branch="main"
        fi
    else
        print_success "Current branch: $current_branch"
    fi

    echo "$current_branch"
}

# ===================================================================
# Main Push Functions
# ===================================================================

# Show what will be pushed
show_changes() {
    print_header "Preview of Changes"

    print_step "Files to be committed:"
    echo ""

    # Show status
    git status --short

    echo ""
    print_step "Commit statistics:"
    echo ""

    # Count files
    local total_files=$(git ls-files | wc -l | tr -d ' ')
    local new_files=$(git ls-files --others --exclude-standard | wc -l | tr -d ' ')
    local modified_files=$(git diff --name-only | wc -l | tr -d ' ')

    echo "  Total files in repo: $total_files"
    echo "  New files:          $new_files"
    echo "  Modified files:     $modified_files"
    echo ""
}

# Stage all files
stage_files() {
    print_step "Staging all files..."

    git add -A

    if [[ $? -eq 0 ]]; then
        print_success "All files staged"
    else
        print_error "Failed to stage files"
        exit 1
    fi
}

# Create commit
create_commit() {
    print_step "Creating commit..."

    git commit -m "$COMMIT_MESSAGE"

    if [[ $? -eq 0 ]]; then
        print_success "Commit created"
    else
        print_warning "Commit may have failed or there are no changes"
    fi
}

# Push to GitHub
push_to_github() {
    local branch="$1"

    print_step "Pushing to GitHub..."
    echo ""

    git push -u origin "$branch"

    if [[ $? -eq 0 ]]; then
        print_success "Successfully pushed to GitHub!"
        return 0
    else
        print_error "Failed to push to GitHub"
        echo ""
        echo "Common issues:"
        echo "  1. Authentication failed - use Personal Access Token"
        echo "  2. Repository doesn't exist - create it on GitHub first"
        echo "  3. Push rejected - try: git pull --rebase origin $branch"
        echo ""
        return 1
    fi
}

# ===================================================================
# Main Script
# ===================================================================

main() {
    # Print welcome banner
    echo ""
    echo -e "${PURPLE}${SPARKLES}════════════════════════════════════════════════════════${SPARKLES}${NC}"
    echo -e "${WHITE}     DevelopmentTranslation Bridge 3.0 - GitHub Push     ${NC}"
    echo -e "${PURPLE}${SPARKLES}════════════════════════════════════════════════════════${SPARKLES}${NC}"
    echo ""

    # Run validation checks
    print_header "Step 1: Validation"
    check_git_installed
    check_git_repo
    check_git_config
    check_remote
    local branch=$(check_branch)

    # Show what will be pushed
    print_header "Step 2: Preview"
    show_changes

    # Confirm push
    print_header "Step 3: Confirmation"
    echo ""
    echo "You are about to:"
    echo "  1. Stage all files"
    echo "  2. Create a commit with the v3.0 release message"
    echo "  3. Push to: $REPO_URL"
    echo "  4. Branch: $branch"
    echo ""

    if ! confirm "Do you want to continue?" "y"; then
        print_warning "Push cancelled by user"
        exit 0
    fi

    # Execute push
    print_header "Step 4: Pushing to GitHub"
    stage_files
    create_commit

    if push_to_github "$branch"; then
        # Success!
        print_header "🎉 Success!"
        echo ""
        echo -e "${GREEN}${ROCKET} Your DevelopmentTranslation Bridge 3.0 is now on GitHub!${NC}"
        echo ""
        echo "Next steps:"
        echo ""
        echo "  1. Visit your repository:"
        echo "     ${CYAN}https://github.com/coryhubbell/development-translation-bridge${NC}"
        echo ""
        echo "  2. Create a release:"
        echo "     - Go to Releases → New Release"
        echo "     - Tag: v3.0.0"
        echo "     - Title: Translation Bridge™ - World's First Framework Translator"
        echo "     - Copy release notes from RELEASE_NOTES.md"
        echo ""
        echo "  3. Share with the community:"
        echo "     - Twitter/X, Reddit r/wordpress, LinkedIn"
        echo "     - Use hashtags: #WordPress #WebDev #AI"
        echo ""
        echo -e "${PURPLE}${SPARKLES} The WordPress community is waiting! ${SPARKLES}${NC}"
        echo ""
    else
        print_header "Push Failed"
        echo ""
        echo "Don't worry! Try these solutions:"
        echo ""
        echo "  1. Check authentication:"
        echo "     - Use Personal Access Token instead of password"
        echo "     - Or setup SSH keys (see .claude-code/SETUP_GUIDE.md)"
        echo ""
        echo "  2. Create repository on GitHub first:"
        echo "     - Go to https://github.com/new"
        echo "     - Name: development-translation-bridge"
        echo "     - Don't initialize with README"
        echo ""
        echo "  3. Try manual push:"
        echo "     git push -u origin $branch"
        echo ""
        echo "For detailed help, see:"
        echo "  - .claude-code/SETUP_GUIDE.md"
        echo "  - TERMINAL_COMMANDS.md"
        echo ""
        exit 1
    fi
}

# Run main function
main

exit 0
