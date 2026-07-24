"""
Batch Processor

Seçili içerikleri toplu olarak video pipeline'a gönderme.
"""

import json
import sys
import subprocess
from datetime import datetime, timezone
from pathlib import Path

class BatchProcessor:
    """Toplu içerik işleme sınıfı"""
    
    def __init__(self, pool_path='data/content_pool.json', jobs_dir='data/jobs'):
        self.pool_path = Path(pool_path)
        self.jobs_dir = Path(jobs_dir)
        
    def load_content_pool(self):
        """İçerik havuzunu yükle"""
        try:
            with open(self.pool_path, 'r', encoding='utf-8') as f:
                return json.load(f)
        except FileNotFoundError:
            return {'content': [], 'metadata': {}}
    
    def save_content_pool(self, pool_data):
        """İçerik havuzunu kaydet"""
        pool_data['metadata']['last_updated'] = datetime.now(timezone.utc).isoformat()
        
        with open(self.pool_path, 'w', encoding='utf-8') as f:
            json.dump(pool_data, f, indent=2, ensure_ascii=False)
    
    def create_job(self, content_item, template='short_haber', video_width=1080, video_height=1920):
        """
        Tek bir içerik için job oluştur
        
        Args:
            content_item: İçerik objesi
            template: Video template
            video_width: Video genişliği
            video_height: Video yüksekliği
            
        Returns:
            str: Job ID veya None
        """
        try:
            # Job ID oluştur
            timestamp = datetime.now().timestamp()
            job_id = f"job_{hex(int(timestamp))[2:]}"
            
            # Job data oluştur
            job_data = {
                'id': job_id,
                'url': content_item['url'],
                'status': 'pending',
                'template': template,
                'videoWidth': video_width,
                'videoHeight': video_height,
                'title': content_item.get('title', ''),
                'created_at': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                'content_source': {
                    'content_id': content_item['id'],
                    'source_name': content_item.get('source', ''),
                    'category': content_item.get('metadata', {}).get('category', '')
                }
            }
            
            # Job dosyasını kaydet
            job_file = self.jobs_dir / f"{job_id}.json"
            with open(job_file, 'w', encoding='utf-8') as f:
                json.dump(job_data, f, indent=2, ensure_ascii=False)
            
            print(f"✅ Job oluşturuldu: {job_id}")
            return job_id
            
        except Exception as e:
            print(f"❌ Job oluşturma hatası: {str(e)}")
            return None
    
    def start_pipeline(self, job_id, config_path='data/config.json'):
        """
        Add job to production queue instead of starting pipeline directly
        
        Args:
            job_id: Job ID
            config_path: Config dosya yolu (compatibility)
            
        Returns:
            bool: Başarılı mı?
        """
        try:
            # Add to production queue
            import sys
            sys.path.insert(0, str(Path(__file__).parent.parent))
            from scheduler.production_queue_manager import add_job_to_queue
            
            result = add_job_to_queue(job_id, priority=0, metadata={
                'added_via': 'batch_processor',
                'config_path': config_path
            })
            
            if result['success']:
                print(f"✅ Added to production queue: {job_id} (position {result['position']})")
                return True
            else:
                print(f"❌ Failed to add to queue: {result.get('error', 'Unknown error')}")
                return False
                
        except Exception as e:
            print(f"❌ Queue error: {str(e)}")
            return False
    
    def process_content_batch(self, content_ids, auto_start=True):
        """
        Seçili içerikleri batch olarak işle
        
        Args:
            content_ids: İşlenecek content ID listesi
            auto_start: Pipeline otomatik başlatılsın mı?
            
        Returns:
            dict: İşlem sonucu
        """
        pool_data = self.load_content_pool()
        content_list = pool_data.get('content', [])
        
        results = {
            'success': [],
            'failed': [],
            'total': len(content_ids)
        }
        
        print(f"🔄 {len(content_ids)} içerik işleniyor...")
        
        for content_id in content_ids:
            # İçeriği bul
            content = next((c for c in content_list if c['id'] == content_id), None)
            
            if not content:
                print(f"⚠️  İçerik bulunamadı: {content_id}")
                results['failed'].append(content_id)
                continue
            
            # Zaten işlendi mi?
            if content.get('status') in ['processing', 'completed']:
                print(f"ℹ️  Zaten işleniyor/işlendi: {content['title'][:40]}")
                results['failed'].append(content_id)
                continue
            
            # Job oluştur
            job_id = self.create_job(content)
            
            if not job_id:
                results['failed'].append(content_id)
                continue
            
            # İçerik durumunu güncelle
            content['status'] = 'processing'
            content['processed_job_id'] = job_id
            content['processed_at'] = datetime.now(timezone.utc).isoformat()
            
            # Pipeline'ı başlat (opsiyonel)
            if auto_start:
                self.start_pipeline(job_id)
            
            results['success'].append({
                'content_id': content_id,
                'job_id': job_id,
                'title': content['title']
            })
        
        # Havuzu kaydet
        self.save_content_pool(pool_data)
        
        print(f"\n✅ Başarılı: {len(results['success'])}")
        print(f"❌ Başarısız: {len(results['failed'])}")
        
        return results
    
    def get_pending_content(self, limit=None):
        """
        Bekleyen içerikleri getir
        
        Args:
            limit: Maksimum içerik sayısı
            
        Returns:
            list: Bekleyen içerikler
        """
        pool_data = self.load_content_pool()
        content_list = pool_data.get('content', [])
        
        pending = [c for c in content_list if c.get('status') == 'pending']
        
        if limit:
            return pending[:limit]
        return pending


# CLI Test
if __name__ == '__main__':
    if len(sys.argv) < 2:
        print("Kullanım: python batch_processor.py <content_id1> <content_id2> ...")
        print("\nÖrnek:")
        print("  python batch_processor.py content_abc123 content_def456")
        sys.exit(1)
    
    content_ids = sys.argv[1:]
    
    print("🔄 Batch Processor")
    print("="*50)
    print(f"📝 İşlenecek içerik sayısı: {len(content_ids)}")
    print()
    
    processor = BatchProcessor()
    results = processor.process_content_batch(content_ids, auto_start=True)
    
    print("="*50)
    print("✅ İşlem tamamlandı")
    
    if results['success']:
        print("\n🎉 Başarılı:")
        for item in results['success']:
            print(f"  - {item['title'][:50]}... → {item['job_id']}")
