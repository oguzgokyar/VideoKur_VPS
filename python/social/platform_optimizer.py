"""
Platform-Specific Metadata Optimizer
Generates optimized captions, hashtags for each social platform
"""
import json
import re
from typing import Dict, List, Optional
from pathlib import Path


class PlatformMetadataOptimizer:
    """
    Generate platform-specific optimized metadata.
    Uses Gemini AI when available, falls back to rules.
    """
    
    # Platform-specific limits and best practices
    PLATFORM_SPECS = {
        'tiktok': {
            'max_caption': 2200,
            'optimal_caption': 150,
            'max_hashtags': 8,
            'style': 'casual, trendy, emoji-heavy',
            'required_tags': ['fyp', 'viral'],
            'video_hashtag': None  # TikTok doesn't need #Shorts equivalent
        },
        'instagram': {
            'max_caption': 2200,
            'optimal_caption': 125,
            'max_hashtags': 30,
            'style': 'aesthetic, inspirational, CTA-focused',
            'required_tags': ['reels', 'instagram'],
            'video_hashtag': 'reels'
        },
        'facebook': {
            'max_caption': 63206,
            'optimal_caption': 80,
            'max_hashtags': 10,
            'style': 'informative, shareable, engaging',
            'required_tags': ['reels'],
            'video_hashtag': 'reels'
        },
        'youtube': {
            'max_caption': 5000,
            'optimal_caption': 200,
            'max_hashtags': 15,
            'style': 'SEO-optimized, searchable',
            'required_tags': ['Shorts'],
            'video_hashtag': 'Shorts'
        }
    }
    
    def __init__(self, gemini_key: str = None, model: str = "gemini-2.0-flash"):
        """
        Initialize optimizer
        
        Args:
            gemini_key: Gemini API key for AI generation
            model: Gemini model name
        """
        self.gemini_key = gemini_key
        self.model = model
    
    def optimize_for_platform(
        self,
        platform: str,
        original_title: str,
        script_text: str,
        base_tags: List[str] = None,
        use_ai: bool = True
    ) -> Dict:
        """
        Generate platform-optimized metadata
        
        Args:
            platform: Target platform (tiktok, instagram, facebook, youtube)
            original_title: Original video title
            script_text: Video script/content text
            base_tags: Base tags to include
            use_ai: Use AI for optimization
            
        Returns:
            Dict with 'caption', 'hashtags', 'title' (if applicable)
        """
        platform = platform.lower()
        if platform not in self.PLATFORM_SPECS:
            raise ValueError(f"Unsupported platform: {platform}")
        
        specs = self.PLATFORM_SPECS[platform]
        
        if use_ai and self.gemini_key:
            return self._ai_optimize(platform, original_title, script_text, base_tags, specs)
        else:
            return self._rule_based_optimize(platform, original_title, script_text, base_tags, specs)
    
    def optimize_all_platforms(
        self,
        original_title: str,
        script_text: str,
        base_tags: List[str] = None,
        platforms: List[str] = None,
        use_ai: bool = True
    ) -> Dict[str, Dict]:
        """
        Generate optimized metadata for multiple platforms at once
        
        Args:
            original_title: Original video title
            script_text: Video script
            base_tags: Base tags
            platforms: List of platforms (default: all)
            use_ai: Use AI optimization
            
        Returns:
            Dict mapping platform -> metadata
        """
        if platforms is None:
            platforms = ['tiktok', 'instagram', 'facebook']
        
        results = {}
        for platform in platforms:
            try:
                results[platform] = self.optimize_for_platform(
                    platform, original_title, script_text, base_tags, use_ai
                )
            except Exception as e:
                print(f"Error optimizing for {platform}: {e}")
                results[platform] = self._rule_based_optimize(
                    platform, original_title, script_text, base_tags,
                    self.PLATFORM_SPECS[platform]
                )
        
        return results
    
    def _ai_optimize(
        self,
        platform: str,
        title: str,
        script: str,
        tags: List[str],
        specs: Dict
    ) -> Dict:
        """Use Gemini AI for platform-specific optimization"""
        try:
            import google.genai as genai
            
            prompt = f"""{platform.upper()} için optimize edilmiş metadata oluştur:

Orijinal Başlık: {title}
Video İçeriği: {script[:500]}
Mevcut Etiketler: {', '.join(tags) if tags else 'Yok'}

Platform: {platform.upper()}
Platform Stili: {specs['style']}
Max Caption: {specs['optimal_caption']} karakter (ideal)
Max Hashtag: {specs['max_hashtags']} adet
Zorunlu Hashtag'ler: {', '.join(specs['required_tags'])}

Gereksinimler:
1. Caption: Platform stiline uygun, dikkat çekici, emoji kullan
2. Hashtags: Trend + niche karışımı, {specs['max_hashtags']} adetten fazla değil
3. Hook: İlk 3 saniyeyi yakalayacak metin

Platform Özel Notlar:
- TikTok: Casual, trend takibi, #fyp önemli
- Instagram: Görsel estetik, #reels zorunlu, CTA ekle
- Facebook: Paylaşılabilir, soru sor, tartışma aç

JSON formatında döndür:
{{
    "caption": "...",
    "hashtags": ["tag1", "tag2", ...],
    "hook": "..."
}}"""
            
            client = genai.Client(api_key=self.gemini_key)
            response = client.models.generate_content(
                model=self.model,
                contents=prompt
            )
            text = response.text.strip()
            
            # Extract JSON
            json_match = re.search(r'\{[\s\S]*\}', text)
            if json_match:
                result = json.loads(json_match.group())
                
                # Validate and clean
                result['caption'] = self._truncate(
                    result.get('caption', ''), 
                    specs['max_caption']
                )
                result['hashtags'] = (result.get('hashtags', []) or [])[:specs['max_hashtags']]
                
                # Ensure required tags
                for tag in specs['required_tags']:
                    if tag.lower() not in [t.lower().lstrip('#') for t in result['hashtags']]:
                        result['hashtags'].insert(0, tag)
                
                result['platform'] = platform
                return result
            else:
                raise ValueError("AI response is not valid JSON")
                
        except Exception as e:
            print(f"AI optimize hatası ({platform}): {e}")
            return self._rule_based_optimize(platform, title, script, tags, specs)
    
    def _rule_based_optimize(
        self,
        platform: str,
        title: str,
        script: str,
        tags: List[str],
        specs: Dict
    ) -> Dict:
        """Rule-based metadata generation"""
        
        # Generate caption based on platform
        caption = self._generate_caption(platform, title, script, specs)
        
        # Generate hashtags
        hashtags = self._generate_hashtags(platform, title, script, tags, specs)
        
        # Generate hook
        hook = self._generate_hook(platform, title, script)
        
        return {
            'caption': caption,
            'hashtags': hashtags,
            'hook': hook,
            'platform': platform
        }
    
    def _generate_caption(self, platform: str, title: str, script: str, specs: Dict) -> str:
        """Generate platform-specific caption"""
        
        # Clean title
        title = re.sub(r'[-_]+', ' ', title)
        title = title.strip().title()
        
        if platform == 'tiktok':
            # Short, punchy, emoji
            caption = f"🔥 {title}"
            if len(script) > 50:
                summary = script[:100].strip()
                if '.' in summary:
                    summary = summary[:summary.rfind('.')+1]
                caption = f"{caption}\n\n{summary}"
            caption += "\n\n💬 Yorumlarını bekliyorum!"
            
        elif platform == 'instagram':
            # Aesthetic, CTA focused
            caption = f"✨ {title}\n\n"
            if len(script) > 50:
                summary = script[:150].strip()
                caption += f"{summary}\n\n"
            caption += "📌 Kaydet!\n"
            caption += "💬 Fikrini yaz!\n"
            caption += "👥 Arkadaşını etiketle!"
            
        elif platform == 'facebook':
            # Shareable, question-based
            caption = f"🤔 {title}\n\n"
            if len(script) > 50:
                summary = script[:200].strip()
                caption += f"{summary}\n\n"
            caption += "💭 Sen ne düşünüyorsun? Yorumlarda paylaş!"
            
        else:
            caption = title
        
        return self._truncate(caption, specs['max_caption'])
    
    def _generate_hashtags(
        self,
        platform: str,
        title: str,
        script: str,
        existing_tags: List[str],
        specs: Dict
    ) -> List[str]:
        """Generate platform-specific hashtags"""
        
        hashtags = []
        
        # Add required tags first
        for tag in specs['required_tags']:
            if tag not in hashtags:
                hashtags.append(tag)
        
        # Add existing tags
        if existing_tags:
            for tag in existing_tags:
                clean_tag = tag.lstrip('#').lower()
                if clean_tag not in [h.lower() for h in hashtags]:
                    hashtags.append(clean_tag)
        
        # Extract keywords from title
        title_words = re.findall(r'\w+', title.lower())
        for word in title_words:
            if len(word) > 3 and word not in hashtags:
                hashtags.append(word)
                if len(hashtags) >= specs['max_hashtags']:
                    break
        
        # Platform-specific trending tags
        platform_tags = {
            'tiktok': ['keşfet', 'türkiye', 'trend', 'viral', 'fyp'],
            'instagram': ['explore', 'instagood', 'trending', 'viral', 'türkiye'],
            'facebook': ['trending', 'viral', 'gündem', 'haber']
        }
        
        for tag in platform_tags.get(platform, []):
            if len(hashtags) < specs['max_hashtags'] and tag not in hashtags:
                hashtags.append(tag)
        
        return hashtags[:specs['max_hashtags']]
    
    def _generate_hook(self, platform: str, title: str, script: str) -> str:
        """Generate attention-grabbing hook for first 3 seconds"""
        
        # Extract first sentence
        first_sentence = script.split('.')[0] if script else title
        first_sentence = first_sentence.strip()[:100]
        
        hooks = {
            'tiktok': f"Bunu bilmen lazım! 👀 {first_sentence}",
            'instagram': f"Kaydır ve öğren ✨ {first_sentence}",
            'facebook': f"Bunu duydun mu? 🤔 {first_sentence}"
        }
        
        return hooks.get(platform, first_sentence)
    
    def _truncate(self, text: str, max_length: int) -> str:
        """Truncate text to max length"""
        if not text:
            return ""
        return text[:max_length] if len(text) > max_length else text


def main():
    """Test the optimizer"""
    optimizer = PlatformMetadataOptimizer()
    
    title = "yapay-zeka-is-yukunu-azaltacagina-artirdi-mi"
    script = """Yapay zeka iş yükünüzü azaltacağını mı sanıyordunuz? 
    Amazon çalışanları yanıldığımızı söylüyor! Teknoloji dünyasının büyük vaadi şuydu..."""
    
    print("🎯 Platform Bazlı Metadata Optimizasyonu")
    print("=" * 60)
    
    for platform in ['tiktok', 'instagram', 'facebook']:
        result = optimizer.optimize_for_platform(
            platform, title, script, ['teknoloji', 'yapay_zeka'], use_ai=False
        )
        
        print(f"\n📱 {platform.upper()}")
        print("-" * 40)
        print(f"Caption:\n{result['caption'][:200]}...")
        print(f"\nHashtags: {' '.join(['#' + h for h in result['hashtags']])}")
        print(f"\nHook: {result['hook']}")


if __name__ == '__main__':
    main()
