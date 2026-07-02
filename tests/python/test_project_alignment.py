"""Project-level alignment checks for public metadata and release hygiene."""

import json
import re
from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]


def read_text(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def json_file(path: str) -> dict:
    return json.loads(read_text(path))


def pyproject_version() -> str:
    match = re.search(r'^version = "([^"]+)"$', read_text("pyproject.toml"), re.MULTILINE)
    assert match is not None
    return match.group(1)


def assert_regex_version(path: str, pattern: str, version: str) -> None:
    text = read_text(path)
    match = re.search(pattern, text, re.MULTILINE)
    assert match is not None, f"{path} missing version pattern: {pattern}"
    assert match.group(1) == version


def test_version_surfaces_match_current_release():
    version = pyproject_version()

    assert json_file("composer.json")["version"] == version
    assert json_file("package.json")["version"] == version
    assert json_file("admin/package.json")["version"] == version

    admin_lock = json_file("admin/package-lock.json")
    assert admin_lock["version"] == version
    assert admin_lock["packages"][""]["version"] == version

    assert_regex_version("src/translation_bridge/__init__.py", r'__version__ = "([^"]+)"', version)
    assert_regex_version("devtb", r'VERSION="([^"]+)"', version)
    assert_regex_version("devtb-php", r"define\('DEVTB_VERSION', '([^']+)'\)", version)
    assert_regex_version("functions.php", r"define\('DEVTB_THEME_VERSION', '([^']+)'\)", version)
    assert_regex_version("style.css", r"^Version: ([^\n]+)$", version)
    assert_regex_version("includes/class-devtb-config.php", r"public const VERSION = '([^']+)';", version)
    assert_regex_version(
        "admin/src/components/Layout/Toolbar.tsx",
        r"window\.devtbData\?\.version \|\| '([^']+)'",
        version,
    )
    assert f"Translation Bridge System v{version}" in read_text("style.css")


def test_license_file_exists_and_readme_points_to_it():
    license_text = read_text("LICENSE")

    assert "GNU GENERAL PUBLIC LICENSE" in license_text
    assert "Version 2, June 1991" in license_text
    assert "[![License]" in read_text("README.md")


def test_public_docs_do_not_restore_old_framework_matrix():
    stale_phrases = [
        "10 supported frameworks",
        "90 translation pairs",
        "5 supported frameworks",
        "5 major page builders",
        "Claude AI-Optimized HTML",
        '"claude-ai"',
        "AI-Powered WordPress Development Framework",
        "docs.devtb.io",
    ]
    checked_files = [
        ".claude/skills/translation-bridge/SKILL.md",
        "docs/api-v2.md",
        "docs/CONVERSION_EXAMPLES.md",
        "docs/FRAMEWORK_MAPPINGS.md",
        "docs/TRANSLATION_BRIDGE.md",
        "package.json",
        "pyproject.toml",
        "tests/fixtures/README.md",
    ]

    for path in checked_files:
        text = read_text(path)
        for phrase in stale_phrases:
            assert phrase not in text, f"{path} contains stale phrase: {phrase}"


def test_release_workflow_requires_license_and_drops_macos_artifacts():
    workflow = read_text(".github/workflows/release.yml")
    package_script = read_text("scripts/build-release-package.sh")

    assert "./scripts/build-release-package.sh" in workflow
    assert "test -f LICENSE" in package_script
    assert "find release -name '.DS_Store' -delete" in package_script
    assert "-x '*/.DS_Store'" in package_script
    assert "admin/dist" in package_script
    assert "manifest" in package_script
    assert "admin/node_modules" in package_script
    assert "This asset is the WordPress theme package." in workflow


def test_admin_node_floor_matches_ci_and_docs():
    admin_package = json_file("admin/package.json")
    ci = read_text(".github/workflows/ci.yml")
    release = read_text(".github/workflows/release.yml")
    node_floor = "^20.19.0 || ^22.13.0 || >=24"

    assert admin_package["engines"]["node"] == node_floor
    assert "node: ['20.19.0', '22.13.0', '24']" in ci
    assert "node-version: '20.19.0'" in release
    assert "node: ['18'" not in ci
    assert "Node **20.19.0**, **22.13.0+**, or **24+**" in read_text("README.md")
    assert "Node.js 20.19.0, 22.13.0+, or 24+" in read_text("CONTRIBUTING.md")


def test_dependabot_covers_all_package_ecosystems():
    config = read_text(".github/dependabot.yml")

    for ecosystem in ("composer", "npm", "pip", "github-actions", "docker-compose"):
        assert f'package-ecosystem: "{ecosystem}"' in config
