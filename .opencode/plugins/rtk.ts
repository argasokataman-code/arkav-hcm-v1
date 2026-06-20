import type { Plugin } from "@opencode-ai/plugin"

// RTK OpenCode plugin — rewrites commands to use rtk for token savings.
// Requires: rtk >= 0.23.0 in PATH.
//
// This is a thin delegating plugin: all rewrite logic lives in `rtk rewrite`,
// which is the single source of truth (src/discover/registry.rs).
// To add or change rewrite rules, edit the Rust registry — not this file.

const RTK_BIN = "/Users/vanviakingali/.local/bin/rtk"

export const RtkOpenCodePlugin: Plugin = async ({ $ }) => {
  try {
    await $`test -x ${RTK_BIN}`.quiet()
  } catch {
    console.warn("[rtk] rtk binary not found — plugin disabled")
    return {}
  }

  return {
    "tool.execute.before": async (input, output) => {
      const tool = String(input?.tool ?? "").toLowerCase()
      if (tool !== "bash" && tool !== "shell") return
      const args = output?.args
      if (!args || typeof args !== "object") return

      const command = (args as Record<string, unknown>).command
      if (typeof command !== "string" || !command) return

      // Skip rewriting for npm, git, gh, curl, and deployment commands
      const trimmed = command.trim()
      if (/^\s*(npm|npx|git|gh|ssh|deploy|curl|python|python3|ls|cat|echo|php)\b/.test(trimmed)) return

      try {
        const result = await $`${RTK_BIN} rewrite ${command}`.quiet().nothrow()
        const rewritten = String(result.stdout).trim()
        if (rewritten && rewritten !== command) {
          ;(args as Record<string, unknown>).command = rewritten
        }
      } catch {
        // rtk rewrite failed — pass through unchanged
      }
    },
  }
}
