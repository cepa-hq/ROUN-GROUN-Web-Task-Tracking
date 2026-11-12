# Instructions for all AI agents working on Roun Groun

Read this file and `docs/agent-coordination/README.md` before any Roun Groun website work. These rules apply to all participating agents, regardless of which AI tool they use.

## Repository boundaries

- This repository (`cepa-hq/ROUN-GROUN-Web-Task-Tracking`) is the coordination hub, not the confirmed website source repository.
- Confirm the website source repository with the project owner before editing website code.
- Staging URL supplied by the owner: https://testarea.roungroun.com
- Do not deploy to staging or production unless the owner explicitly authorizes it.

## Mandatory pre-change process

1. Read this file and the coordination workflow.
2. Inspect open issues in this coordination repository.
3. Inspect the source repository's default branch, active branches, open pull requests, recent commits and project instructions.
4. Before editing website source code, create a visible GitHub Issue here using the task-plan template. Include:
   - agent identifier and task objective;
   - reason for the change and bug evidence/reproduction, if applicable;
   - source repository, base branch/head commit and proposed task branch;
   - expected file paths, pages, routes, components, APIs, schemas and shared styles affected;
   - files/surfaces explicitly out of scope;
   - other active tasks checked and potential overlap;
   - implementation approach, risks and test/verification plan.
5. Do not start source-code edits until the plan is visible and scope overlap has been resolved.
6. Re-check open coordination issues and source-repository pull requests immediately before editing. If scope overlaps with active work, coordinate in the issue before proceeding.

## Isolation and change safety

- Use a unique branch per task and agent, for example `agent/<agent-id>/rg-<issue-number>-short-description`.
- Never work directly on the source repository's shared default branch.
- Never use another agent's branch as your working branch.
- Never overwrite, reset, force-push, revert or discard another agent's work.
- Watch for indirect overlap: shared components, global CSS, route registries, package manifests, generated assets, database schemas, API contracts and build configuration.
- Keep changes within the declared scope.
- If additional files or shared surfaces become necessary, update the coordination issue and resolve conflicts before editing them.
- If an unexplained diff, unexpected upstream change, conflict or uncertain ownership is found, stop and report it. Do not resolve another agent's work unilaterally.
- Do not commit credentials, tokens, environment files, customer data or secrets.

## Pull requests, review and deployment

- Commit coherent task-sized changes with descriptive messages.
- Push only your own task branch.
- Open a pull request and link the coordination issue.
- Include changed files, rationale, real test results, limitations and deployment impact.
- Do not merge another agent's PR.
- Do not merge your own PR or deploy unless the project owner explicitly authorizes it.
- Re-check the target branch and related PRs before requesting merge.

## Completion record

Before marking work complete, update the coordination issue with the completion-report template:
- actual files and code sections altered;
- why each change was made and bugs fixed;
- tests run and actual results (never claim unrun tests passed);
- source branch, commit SHA and pull-request URL;
- staging/deployment status and remaining risks.

Do not close a task issue merely because code was written. Follow the project's review and merge process.

## Honest coordination

GitHub Issues and branches are coordination mechanisms, not atomic locks. They cannot reveal another agent's local or unpushed work. Never claim that simultaneous edits are impossible or that unpushed changes have been detected. Agents must publish plans and branches early and communicate when overlap is discovered.
