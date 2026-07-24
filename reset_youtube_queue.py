"""
Reset YouTube Upload Queue
Tıkanan YouTube kuyruğunu temizle ve failed videoları yeniden kuyruğa ekle
"""
import json
import os
from pathlib import Path
from datetime import datetime, timezone
import uuid

BASE_DIR = Path(__file__).parent
DATA_DIR = BASE_DIR / 'data'

def main():
    print("=" * 60)
    print("  YouTube Kuyruk Sıfırlama Aracı")
    print("=" * 60)
    print()
    
    # 1. social_history.json'dan YouTube failed olanları bul
    history_file = DATA_DIR / 'social_history.json'
    social_queue_file = DATA_DIR / 'social_queue.json'
    
    if not history_file.exists():
        print("❌ social_history.json bulunamadı")
        return
    
    with open(history_file, 'r', encoding='utf-8') as f:
        history_data = json.load(f)
    
    # YouTube'da failed olan videoları filtrele
    failed_youtube = []
    for item in history_data.get('history', []):
        yt_status = item.get('platform_status', {}).get('youtube', {})
        if yt_status.get('status') == 'failed':
            # Video dosyası var mı kontrol et
            video_path = item.get('video_path', '')
            if video_path and os.path.exists(video_path):
                failed_youtube.append(item)
    
    if not failed_youtube:
        print("✅ YouTube'da failed video yok veya video dosyaları silinmiş")
        return
    
    print(f"📋 {len(failed_youtube)} adet failed video bulundu\n")
    
    # 2. Kullanıcıya onay sor
    print("Bu videolar social_queue.json'a yeniden eklenecek:")
    for i, item in enumerate(failed_youtube[:10], 1):
        title = item.get('metadata', {}).get('title', 'Başlıksız')
        print(f"  {i}. {title[:60]}")
    
    if len(failed_youtube) > 10:
        print(f"  ... ve {len(failed_youtube) - 10} video daha")
    
    print()
    response = input("Devam etmek istiyor musun? (e/h): ").lower()
    
    if response != 'e':
        print("❌ İşlem iptal edildi")
        return
    
    # 3. social_queue.json'ı yükle veya oluştur
    if social_queue_file.exists():
        with open(social_queue_file, 'r', encoding='utf-8') as f:
            queue_data = json.load(f)
    else:
        queue_data = {'queue': []}
    
    # 4. Failed videoları kuyruğa geri ekle
    added_count = 0
    for item in failed_youtube:
        # Yeni queue item oluştur
        queue_item = {
            'queue_id': f"social_{uuid.uuid4().hex[:16]}",
            'job_id': item['job_id'],
            'video_path': item['video_path'],
            'platforms': ['youtube'],  # Sadece YouTube
            'platform_status': {
                'youtube': {
                    'status': 'pending',  # Yeniden pending yap
                    'post_id': None,
                    'post_url': None,
                    'error': None,
                    'uploaded_at': None
                }
            },
            'scheduled_time': datetime.now(timezone.utc).isoformat(),  # Hemen başlat
            'status': 'pending',
            'priority': 0,
            'metadata': item.get('metadata', {}),
            'platform_metadata': {},
            'created_at': datetime.now(timezone.utc).isoformat(),
            'retry_count': 0,
            'last_error': None
        }
        
        queue_data['queue'].append(queue_item)
        added_count += 1
    
    # 5. Kaydet
    with open(social_queue_file, 'w', encoding='utf-8') as f:
        json.dump(queue_data, f, indent=2, ensure_ascii=False)
    
    print()
    print(f"✅ {added_count} video social_queue.json'a eklendi")
    print(f"📂 Dosya: {social_queue_file}")
    print()
    print("🚀 Şimdi social scheduler'ı başlatabilirsin:")
    print("   start_social_scheduler.bat")
    print()
    
    # 6. History'den bu videoları kaldır (opsiyonel)
    response = input("Bu videoları history'den silmek ister misin? (e/h): ").lower()
    
    if response == 'e':
        removed_job_ids = {item['job_id'] for item in failed_youtube}
        history_data['history'] = [
            item for item in history_data['history']
            if item['job_id'] not in removed_job_ids
        ]
        
        with open(history_file, 'w', encoding='utf-8') as f:
            json.dump(history_data, f, indent=2, ensure_ascii=False)
        
        print(f"✅ {len(removed_job_ids)} video history'den silindi")
    
    print()
    print("=" * 60)
    print("✅ İşlem tamamlandı!")
    print("=" * 60)

if __name__ == '__main__':
    main()
