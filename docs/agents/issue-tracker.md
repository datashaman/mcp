# Issue tracker: GitHub

Issues and PRDs for this repo live as GitHub issues in `datashaman/mcp`. Use the `gh` CLI for all operations, and pass `--repo datashaman/mcp` when ambiguity is possible.

## Conventions

- **Create an issue**: `gh issue create --repo datashaman/mcp --title "..." --body "..."`. Use a heredoc for multi-line bodies.
- **Read an issue**: `gh issue view <number> --repo datashaman/mcp --comments`, filtering comments by `jq` and also fetching labels.
- **List issues**: `gh issue list --repo datashaman/mcp --state open --json number,title,body,labels,comments --jq '[.[] | {number, title, body, labels: [.labels[].name], comments: [.comments[].body]}]'` with appropriate `--label` and `--state` filters.
- **Comment on an issue**: `gh issue comment <number> --repo datashaman/mcp --body "..."`
- **Apply / remove labels**: `gh issue edit <number> --repo datashaman/mcp --add-label "..."` / `--remove-label "..."`
- **Close**: `gh issue close <number> --repo datashaman/mcp --comment "..."`

## When a skill says "publish to the issue tracker"

Create a GitHub issue in `datashaman/mcp`.

## When a skill says "fetch the relevant ticket"

Run `gh issue view <number> --repo datashaman/mcp --comments`.
