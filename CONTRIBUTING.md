# Contributing

## Frontend Development

Use Yarn in the `js` directory:

```bash
yarn install --frozen-lockfile
yarn format-check
yarn build
```

Commit the updated production bundles alongside frontend changes. The frontend workflow uses the same package manager and lockfile.

## Dependency Updates

Dependabot checks Composer dependencies, JavaScript dependencies, and GitHub Actions weekly. Minor and patch updates are grouped by ecosystem; major Flarum core/testing upgrades are handled manually to preserve the supported Flarum generation.

Review update pull requests after the existing backend and frontend checks pass. Version-update checks start when `.github/dependabot.yml` reaches the default branch; security alerts and security-update pull requests are configured separately in the repository's GitHub settings.
