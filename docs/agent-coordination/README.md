# Shared agent coordination protocol

## Purpose

Multiple AI agents are contributing to one Roun Groun website. This workflow makes intended work visible before source-code changes begin and preserves a shared record of decisions, code scope, bug fixes and verification.

## Source of truth

**GitHub Issues in this coordination repository are the canonical task ledger.**

- Use one issue for each independently deliverable task.
- Create the issue before editing website source code.
- The issue body is the initial plan; comments record scope changes, coordination decisions, progress and completion.
- Keep the issue open while work is proposed, active or blocked.
- Close it only after the project's review/merge process is complete or the task is cancelled.
- Use the issue number as the task ID: `RG-<issue-number>`.

Do not rely on a single shared `ACTIVE_WORK.md` file as a lock. Concurrent edits to one index file can themselves conflict. The GitHub Issues list and individual issue timelines form the shared ledger.

## Lifecycle

### 1. Discover

Before proposing work, inspect:
- open issues in this coordination repository;
- source-repository open PRs, active branches and recent commits;
- existing `AGENTS.md` and project documentation;
- relevant code and tests.

Record what was actually inspected. A GitHub integration cannot see another agent's unpushed local changes; agents must publish plans and branches early.

### 2. Declare intent

Create an issue titled:

`[PLAN][<agent-id>] <short task description>`

Use [TASK_PLAN_TEMPLATE.md](TASK_PLAN_TEMPLATE.md). Identify the source repository and exact or best-known file/component scope. Mark uncertain paths as provisional.

The plan must be visible before editing begins. If GitHub Issues are unavailable, do not silently skip this step; notify the owner and use an agreed alternative.

### 3. Check overlap and coordinate

Compare the declared scope with all open coordination issues and relevant source-repository PRs.

- **Same file or component:** do not start until agents agree on ownership, split scope or sequence the work.
- **Shared/global surface:** treat package manifests, route registries, shared layouts, global CSS, design tokens, API contracts, schemas, navigation and build configuration as high-risk overlap, even when task descriptions differ.
- **Unclear ownership:** stop and ask in the relevant issue.
- **New scope discovered:** update the coordination issue before editing the newly affected files.

Issues are cooperative declarations, not hard locks. An issue does not prove that no unannounced or local work exists.

### 4. Implement in isolation

Use a unique branch per task and agent:

`agent/<agent-id>/rg-<issue-number>-<slug>`

Do not work on the source repository's default branch or share another agent's branch. Keep commits task-scoped. Review upstream changes before integrating them; never overwrite another agent's work.

### 5. Review and complete

Open a PR from the task branch and link the coordination issue. Include the complete changed-file scope and actual test results. Update the issue using [COMPLETION_REPORT_TEMPLATE.md](COMPLETION_REPORT_TEMPLATE.md).

No agent may merge or deploy without explicit owner authorization.

## Required task record

Every plan and completion record must be understandable without access to the originating chat:

- task/issue number and agent identifier;
- goal, rationale and bug evidence;
- source repository, base branch and task branch;
- intended and actual file paths;
- pages, routes, components, functions, schemas or APIs affected;
- files and surfaces explicitly out of scope;
- dependencies and known overlapping tasks;
- tests, results and limitations;
- commits, PR and deployment status.

## Before starting checklist

- [ ] Read root `AGENTS.md` and this workflow.
- [ ] Check open coordination issues.
- [ ] Inspect source-repository branches, PRs and recent commits.
- [ ] Publish a task-plan issue.
- [ ] Resolve overlapping ownership before editing.

## Before finishing checklist

- [ ] Review the complete diff.
- [ ] Confirm that only declared files/surfaces changed, or document scope changes.
- [ ] Run relevant tests and report actual results.
- [ ] Push only the task branch and open a PR.
- [ ] Update the coordination issue with commit, PR and completion report.
