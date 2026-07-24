"""
Social Media Integration Package
Supports: TikTok, Instagram, Facebook
"""
from .base import BaseSocialUploader, UploadResult
from .platform_optimizer import PlatformMetadataOptimizer

__all__ = [
    'BaseSocialUploader',
    'UploadResult', 
    'PlatformMetadataOptimizer'
]
