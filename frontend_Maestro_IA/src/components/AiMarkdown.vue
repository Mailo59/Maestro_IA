<script setup>
import { computed } from 'vue'
import MarkdownIt from 'markdown-it'

const props = defineProps({
  content: {
    type: String,
    default: '',
  },
})

const markdown = new MarkdownIt({
  breaks: true,
  html: false,
  linkify: true,
  typographer: true,
})

const answerPattern = /^\s*(?:[-*]\s*)?(?:\*\*)?(respuesta|resultado|respuesta final|resultado final)\b/i
const summaryPattern = /^\s{0,3}#{1,4}\s*(resumen para revisar|resumen de respuestas|respuestas detectadas)\b/i

const html = computed(() => renderWithHiddenAnswers(cleanMarkdown(props.content)))

function cleanMarkdown(value) {
  return (value || '')
    .replace(/\$\$/g, '')
    .replace(/\\mathbf\{([^}]+)\}/g, '$1')
    .replace(/\\_/g, '_')
    .replace(/\\\s/g, ' ')
    .replace(/\\{3,}/g, '')
    .replace(/^-{3,}$/gm, '')
    .trim()
}

function renderWithHiddenAnswers(value) {
  const lines = value.split('\n')
  const chunks = []
  let visible = []

  function flushVisible() {
    if (visible.length === 0) return

    chunks.push(markdown.render(visible.join('\n')))
    visible = []
  }

  for (let index = 0; index < lines.length; index += 1) {
    const line = lines[index]

    if (answerPattern.test(line)) {
      flushVisible()
      chunks.push(renderHiddenBlock(line))
      continue
    }

    if (summaryPattern.test(line)) {
      flushVisible()
      const hiddenLines = [line]
      index += 1

      while (index < lines.length && !/^\s{0,3}#{1,3}\s+/.test(lines[index])) {
        hiddenLines.push(lines[index])
        index += 1
      }

      index -= 1
      chunks.push(renderHiddenBlock(hiddenLines.join('\n'), 'Mostrar resumen de respuestas'))
      continue
    }

    visible.push(line)
  }

  flushVisible()

  return chunks.join('')
}

function renderHiddenBlock(markdownText, label = 'Mostrar respuesta correcta') {
  return `
    <details class="hidden-answer">
      <summary>${label}</summary>
      <div class="hidden-answer-content">
        ${markdown.render(markdownText)}
      </div>
    </details>
  `
}
</script>

<template>
  <div class="markdown-body" v-html="html" />
</template>

<style scoped>
.markdown-body {
  background: #ffffff;
  border: 1px solid #d9e2ef;
  border-radius: 8px;
  color: #253041;
  font-size: 16px;
  line-height: 1.75;
  padding: 20px;
}

.markdown-body :deep(h1) {
  border-bottom: 1px solid #e5eaf2;
  font-size: 26px;
  margin: 0 0 18px;
  padding-bottom: 10px;
}

.markdown-body :deep(h2) {
  color: #155eef;
  font-size: 20px;
  margin: 24px 0 10px;
}

.markdown-body :deep(h3) {
  background: #f8fafc;
  border-left: 4px solid #155eef;
  border-radius: 6px;
  font-size: 17px;
  margin: 18px 0 10px;
  padding: 8px 12px;
}

.markdown-body :deep(p) {
  margin: 0 0 12px;
}

.markdown-body :deep(ul) {
  margin: 0 0 12px;
  padding-left: 22px;
}

.markdown-body :deep(li) {
  margin-bottom: 8px;
}

.markdown-body :deep(strong) {
  color: #111827;
}

.markdown-body :deep(.hidden-answer) {
  background: #f8fafc;
  border: 1px solid #cfd6e3;
  border-radius: 8px;
  margin: 14px 0;
  padding: 12px;
}

.markdown-body :deep(.hidden-answer summary) {
  color: #155eef;
  cursor: pointer;
  font-weight: 800;
  list-style: none;
}

.markdown-body :deep(.hidden-answer summary::-webkit-details-marker) {
  display: none;
}

.markdown-body :deep(.hidden-answer-content) {
  border-top: 1px solid #d9e2ef;
  margin-top: 12px;
  padding-top: 12px;
}
</style>
