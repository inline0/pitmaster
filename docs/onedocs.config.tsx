import { defineConfig } from "onedocs/config";
import { GitBranch, Package, Search, Merge, Globe, Terminal, Shield, Layers } from "lucide-react";

const iconClass = "h-5 w-5 text-fd-primary";

export default defineConfig({
  title: "Pitmaster",
  description: "Pure PHP Git implementation. No exec(), no FFI, no extensions.",
  logo: {
    light: "/logo-light.svg",
    dark: "/logo-dark.svg",
  },
  icon: { light: "/icon.png", dark: "/icon-dark.png" },
  nav: {
    github: "inline0/pitmaster",
  },
  footer: {
    links: [{ label: "Inline0.com", href: "https://inline0.com" }],
  },
  homepage: {
    features: [
      {
        title: "Pure PHP",
        description:
          "No exec('git'), no FFI, no extensions beyond zlib and mbstring. Works everywhere PHP runs.",
        icon: <Package className={iconClass} />,
      },
      {
        title: "Read & Write",
        description:
          "Loose objects, pack files with delta resolution, index read/write, refs, and config. Full round-trip.",
        icon: <Layers className={iconClass} />,
      },
      {
        title: "Diff & Merge",
        description:
          "Myers O(ND) diff algorithm, byte-exact with git. Three-way merge with conflict markers.",
        icon: <Merge className={iconClass} />,
      },
      {
        title: "Network Protocol",
        description:
          "Clone, fetch, and push via smart HTTP. Ref discovery, pkt-line, side-band, protocol v1 and v2.",
        icon: <Globe className={iconClass} />,
      },
      {
        title: "Git Operations",
        description:
          "add, commit, status, diff, merge, checkout, reset, stash, cherry-pick, revert, rebase, blame, grep.",
        icon: <GitBranch className={iconClass} />,
      },
      {
        title: "CLI",
        description:
          "18-command CLI mirroring git: log, status, diff, add, commit, branch, merge, stash, blame, grep.",
        icon: <Terminal className={iconClass} />,
      },
      {
        title: "Oracle-Tested",
        description:
          "637 scenarios verified against canonical git, using fixtures from libgit2, go-git, isomorphic-git, dulwich, JGit, and git's own test suite.",
        icon: <Shield className={iconClass} />,
      },
      {
        title: "Full Search",
        description:
          "Blame, grep, bisect, notes, log with path filter. Search through history without shelling out.",
        icon: <Search className={iconClass} />,
      },
    ],
  },
});
