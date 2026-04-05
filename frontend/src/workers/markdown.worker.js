import MarkdownIt from 'markdown-it'

const md = new MarkdownIt({
  html: false,
  linkify: true,
  typographer: true,
  breaks: true,
})

self.onmessage = ({ data }) => {
  const { id, content } = data
  const html = md.render(content || '')
  self.postMessage({ id, html })
}
