"""
Content Discovery and Management Module

Bu modül içerik keşfi, toplama ve yönetimi için araçlar sağlar:
- RSS feed parsing
- İçerik skorlama
- Batch processing
- Otomatik scheduler
"""

__version__ = "1.0.0"
__author__ = "Video_Kur"

from .feed_parser import FeedParser
# from .content_scorer import ContentScorer  # DISABLED: File not implemented yet
from .batch_processor import BatchProcessor

__all__ = ['FeedParser', 'BatchProcessor']
