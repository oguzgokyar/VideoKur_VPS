"""
RSS Feed Parser

RSS feed'lerden içerik toplama ve parsing işlemleri.
"""

import feedparser
import json
import hashlib
from datetime import datetime, timezone
from pathlib import Path
import time

class FeedParser:
    """RSS feed parsing ve içerik toplama sınıfı"""
    
    def __init__(self, config_path='data/content_sources.json', pool_path='data/content_pool.json'):
        self.config_path = Path(config_path)
        self.pool_path = Path(pool_path)
        
    def load_sources(self):
        """RSS kaynaklarını yükle"""
        try:
            with open(self.config_path, 'r', encoding='utf-8') as f:
                data = json.load(f)
                return data.get('sources', [])
        except FileNotFoundError:
            return []
        except json.JSONDecodeError:
            print(f"[ERROR] Hata: {self.config_path} JSON formatı geçersiz")
            return []
    
    def load_content_pool(self):
        """Mevcut içerik havuzunu yükle"""
        try:
            with open(self.pool_path, 'r', encoding='utf-8') as f:
                return json.load(f)
        except FileNotFoundError:
            return {'content': [], 'metadata': {}}
        except json.JSONDecodeError:
            return {'content': [], 'metadata': {}}
    
    def save_content_pool(self, pool_data):
        """İçerik havuzunu kaydet"""
        pool_data['metadata']['last_updated'] = datetime.now(timezone.utc).isoformat()
        pool_data['metadata']['total_items'] = len(pool_data.get('content', []))
        
        with open(self.pool_path, 'w', encoding='utf-8') as f:
            json.dump(pool_data, f, indent=2, ensure_ascii=False)
    
    def generate_content_id(self, url):
        """URL'den unique ID oluştur"""
        hash_obj = hashlib.md5(url.encode())
        return f"content_{hash_obj.hexdigest()[:12]}"
    
    def content_exists(self, pool_data, url):
        """İçerik zaten havuzda mı?"""
        content_list = pool_data.get('content', [])
        return any(item['url'] == url for item in content_list)
    
    def parse_feed(self, source, max_items=20):
        """Tek bir RSS feed'i parse et"""
        print(f"[RSS] Feed kontrol ediliyor: {source['name']}")
        
        try:
            # Feed'i çek
            feed = feedparser.parse(source['url'])
            
            if feed.bozo:
                print(f"[WARN]  Feed parse hatası: {source['name']}")
                return []
            
            # Yeni içerikleri topla
            new_contents = []
            
            for entry in feed.entries[:max_items]:  # Limit uygula
                # URL kontrolü
                url = entry.get('link', '')
                if not url:
                    continue
                
                # Başlık kontrolü
                title = entry.get('title', 'Başlık Yok')
                
                # Yayın tarihi
                published = entry.get('published_parsed', None)
                if published:
                    published_dt = datetime(*published[:6], tzinfo=timezone.utc)
                else:
                    published_dt = datetime.now(timezone.utc)
                
                # İçerik oluştur
                content_item = {
                    'id': self.generate_content_id(url),
                    'url': url,
                    'title': title,
                    'source': source['name'],
                    'source_id': source['id'],
                    'source_type': 'rss',
                    'discovered_at': datetime.now(timezone.utc).isoformat(),
                    'published_at': published_dt.isoformat(),
                    'status': 'pending',
                    'processed_job_id': None,
                    'metadata': {
                        'keywords': source.get('keywords', []),
                        'category': source.get('category', 'genel'),
                        'description': entry.get('summary', '')[:200]
                    }
                }
                
                new_contents.append(content_item)
            
            print(f"[OK] {len(new_contents)} yeni içerik bulundu: {source['name']}")
            return new_contents
            
        except Exception as e:
            print(f"[ERROR] Feed parse hatası ({source['name']}): {str(e)}")
            return []
    
    def fetch_all_feeds(self, source_id=None, limit=20):
        """Tüm RSS feed'lerden içerik topla
        
        Args:
            source_id: Belirli bir kaynak ID'si (None ise tümü)
            limit: Kaynak başına maksimum içerik sayısı
        """
        sources = self.load_sources()
        pool_data = self.load_content_pool()
        
        # Kaynakları filtrele
        if source_id:
            enabled_sources = [s for s in sources if s['id'] == source_id and s.get('enabled', True)]
        else:
            enabled_sources = [s for s in sources if s.get('enabled', True)]
        
        if not enabled_sources:
            print("[WARN]  Aktif RSS kaynağı bulunamadı")
            return 0
        
        print(f"[START] {len(enabled_sources)} RSS kaynağı kontrol ediliyor (limit: {limit})...")
        
        total_new = 0
        
        for source in enabled_sources:
            # Feed'i parse et
            new_contents = self.parse_feed(source, max_items=limit)
            
            # Yeni içerikleri ekle
            for content in new_contents:
                if not self.content_exists(pool_data, content['url']):
                    if 'content' not in pool_data:
                        pool_data['content'] = []
                    pool_data['content'].append(content)
                    total_new += 1
            
            # Rate limiting
            if len(enabled_sources) > 1:
                time.sleep(2)
        
        # Havuzu kaydet
        if total_new > 0:
            self.save_content_pool(pool_data)
            print(f"[OK] Toplam {total_new} yeni içerik eklendi")
        else:
            print("[INFO]  Yeni içerik bulunamadı")
        
        return total_new
    
    def update_source_last_checked(self, source_id):
        """Kaynağın son kontrol zamanını güncelle"""
        try:
            with open(self.config_path, 'r', encoding='utf-8') as f:
                data = json.load(f)
            
            for source in data.get('sources', []):
                if source['id'] == source_id:
                    source['last_checked'] = datetime.now(timezone.utc).isoformat()
                    break
            
            with open(self.config_path, 'w', encoding='utf-8') as f:
                json.dump(data, f, indent=2, ensure_ascii=False)
                
        except Exception as e:
            print(f"Warning: Could not update last checked time: {str(e)}")


# CLI Test
if __name__ == '__main__':
    import argparse
    import sys
    import io
    
    # Windows console encoding fix
    if sys.platform == 'win32':
        sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
        sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8', errors='replace')
    
    arg_parser = argparse.ArgumentParser(description='RSS Feed Parser')
    arg_parser.add_argument('--source-id', type=str, help='Specific source ID to fetch')
    arg_parser.add_argument('--limit', type=int, default=20, help='Max items per source')
    args = arg_parser.parse_args()
    
    print("RSS Feed Parser")
    print("="*50)
    
    parser = FeedParser()
    
    # Eğer belirli bir kaynak seçildiyse sadece onu çek
    if args.source_id:
        sources = parser.load_sources()
        sources = [s for s in sources if s['id'] == args.source_id and s.get('enabled', True)]
        if not sources:
            print(f"Source not found: {args.source_id}")
            exit(1)
    
    new_count = parser.fetch_all_feeds(source_id=args.source_id, limit=args.limit)
    
    print("="*50)
    print(f"Added {new_count} new items")

