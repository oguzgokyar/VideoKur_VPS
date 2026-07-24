import requests
from newspaper import Article
from bs4 import BeautifulSoup

def scrape_news(url: str) -> dict:
    """Haber URL'sinden başlık, metin ve görselleri çeker."""
    try:
        article = Article(url, language='tr')
        article.download()
        article.parse()

        title = article.title or ''
        text = article.text or ''
        top_image = article.top_image or ''
        images = list(article.images) if article.images else []

        if not text:
            resp = requests.get(url, timeout=15, headers={'User-Agent': 'Mozilla/5.0'})
            soup = BeautifulSoup(resp.text, 'lxml')
            paragraphs = soup.find_all('p')
            text = '\n'.join(p.get_text(strip=True) for p in paragraphs if len(p.get_text(strip=True)) > 30)
            if not title:
                title_tag = soup.find('h1')
                title = title_tag.get_text(strip=True) if title_tag else ''

        return {
            'title': title,
            'text': text,
            'top_image': top_image,
            'images': images[:5],
            'url': url
        }
    except Exception as e:
        return {'title': '', 'text': '', 'top_image': '', 'images': [], 'url': url, 'error': str(e)}


if __name__ == '__main__':
    import sys, json
    if len(sys.argv) > 1:
        result = scrape_news(sys.argv[1])
        print(json.dumps(result, ensure_ascii=False, indent=2))
