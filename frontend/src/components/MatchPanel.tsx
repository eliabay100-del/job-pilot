"use client";

import { Badge, cx } from "./ui";
import {
  COMPONENT_LABELS,
  flagLabel,
  formatScore,
  scoreBarClass,
  type ComponentScore,
  type MatchExplanation,
} from "@/lib/matches";

export function ScoreBar({
  score,
  className,
  label,
}: {
  score: number;
  className?: string;
  label?: string;
}) {
  return (
    <div
      className={cx(
        "h-2 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800",
        className,
      )}
      role="img"
      aria-label={label ?? `Score ${formatScore(score)}`}
    >
      <div
        className={cx("h-full rounded-full transition-all", scoreBarClass(score))}
        style={{ width: `${Math.max(2, Math.min(100, score))}%` }}
      />
    </div>
  );
}

export function ScoreSummary({ score, recommendation }: { score: number; recommendation: string | null }) {
  // Static class names: Tailwind cannot see interpolated values at build time.
  const tone =
    score >= 80
      ? "text-green-700 dark:text-green-300"
      : score >= 60
        ? "text-blue-700 dark:text-blue-300"
        : score >= 40
          ? "text-amber-600 dark:text-amber-300"
          : "text-zinc-500 dark:text-zinc-400";

  return (
    <div className="w-full sm:w-56">
      <div className="flex items-baseline justify-between gap-2">
        <span className="text-xs font-medium text-zinc-500">Your match</span>
        <span className={cx("text-2xl font-bold tabular-nums", tone)}>{formatScore(score)}</span>
      </div>
      <ScoreBar score={score} className="mt-1.5" label={`Your match ${formatScore(score)}`} />
      {recommendation && <p className="mt-1.5 text-xs text-zinc-500">{recommendation}</p>}
    </div>
  );
}

function ComponentRow({ name, component }: { name: string; component: ComponentScore }) {
  const label = COMPONENT_LABELS[name] ?? name;

  if (!component.applied) {
    return (
      <li className="flex items-baseline justify-between gap-3 text-sm">
        <span className="text-zinc-600 dark:text-zinc-300">{label}</span>
        <span className="text-right text-xs text-zinc-400">
          Not scored — {component.detail ?? "no data on either side"}
        </span>
      </li>
    );
  }

  const score = component.score ?? 0;

  return (
    <li>
      <div className="flex items-baseline justify-between gap-3 text-sm">
        <span className="text-zinc-700 dark:text-zinc-200">{label}</span>
        <span className="tabular-nums text-xs font-medium text-zinc-600 dark:text-zinc-300">
          {formatScore(score)}
          <span className="ml-1 font-normal text-zinc-400">· weight {component.weight}</span>
        </span>
      </div>
      <ScoreBar score={score} className="mt-1 h-1.5" label={`${label} ${formatScore(score)}`} />
      {component.detail && <p className="mt-1 text-xs text-zinc-500">{component.detail}</p>}
    </li>
  );
}

export function ComponentBreakdown({ components }: { components: Record<string, ComponentScore> }) {
  const entries = Object.entries(components).sort((a, b) => b[1].weight - a[1].weight);

  return (
    <ul className="space-y-3">
      {entries.map(([name, component]) => (
        <ComponentRow key={name} name={name} component={component} />
      ))}
    </ul>
  );
}

function SkillChips({
  items,
  tone,
}: {
  items: Array<{ id: number; name: string }>;
  tone: "green" | "amber";
}) {
  return (
    <div className="flex flex-wrap gap-1.5">
      {items.map((item) => (
        <span
          key={item.id}
          className={cx(
            "rounded-md px-2 py-0.5 text-xs",
            tone === "green"
              ? "bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200"
              : "bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200",
          )}
        >
          {tone === "green" ? "✓ " : "△ "}
          {item.name}
        </span>
      ))}
    </div>
  );
}

/** The "why this matches you" block required by SPEC section 19/46. */
export function MatchPanel({ match }: { match: MatchExplanation }) {
  return (
    <div className="space-y-4">
      <ScoreSummary score={match.overall_score} recommendation={match.recommendation} />

      {match.hard_requirement_flags.length > 0 && (
        <div className="flex flex-wrap gap-1.5">
          {match.hard_requirement_flags.map((flag) => (
            <Badge key={flag} tone={flag === "insufficient_data" ? "zinc" : "amber"}>
              {flagLabel(flag)}
            </Badge>
          ))}
        </div>
      )}

      {match.matched_skills.length > 0 && (
        <div>
          <p className="mb-1.5 text-xs font-semibold text-zinc-700 dark:text-zinc-200">
            Strong matches
          </p>
          <SkillChips items={match.matched_skills} tone="green" />
        </div>
      )}

      {match.missing_skills.length > 0 && (
        <div>
          <p className="mb-1.5 text-xs font-semibold text-zinc-700 dark:text-zinc-200">
            Missing skills
          </p>
          <SkillChips items={match.missing_skills} tone="amber" />
        </div>
      )}

      {match.weak_areas.length > 0 && (
        <ul className="space-y-1 text-xs text-amber-700 dark:text-amber-300">
          {match.weak_areas.map((area, index) => (
            <li key={`${area.type}-${index}`}>△ {area.message ?? area.name ?? area.type}</li>
          ))}
        </ul>
      )}

      <details className="rounded-lg border border-zinc-200 dark:border-zinc-800">
        <summary className="cursor-pointer px-3 py-2 text-xs font-medium text-zinc-600 dark:text-zinc-300">
          How this score was calculated
        </summary>
        <div className="space-y-3 border-t border-zinc-200 px-3 py-3 dark:border-zinc-800">
          <ComponentBreakdown components={match.component_scores} />
          <p className="text-xs text-zinc-500">
            Weighted score {formatScore(match.weighted_score)}
            {match.penalty > 0 ? ` − ${match.penalty} points of hard-requirement penalties` : ""} ={" "}
            <strong className="tabular-nums">{formatScore(match.overall_score)}</strong>. Model{" "}
            {match.model_version}, computed {new Date(match.computed_at).toLocaleString()}.
          </p>
        </div>
      </details>
    </div>
  );
}
