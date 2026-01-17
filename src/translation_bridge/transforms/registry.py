"""
Translation Bridge v4 - Transform and Parser Registry.

Provides centralized registration and discovery of transforms and parsers
for different page builder frameworks.
"""

from typing import Any, Callable, Dict, List, Optional, Type
from dataclasses import dataclass


@dataclass
class TransformInfo:
    """Information about a registered transform."""

    name: str
    source_framework: str
    target_framework: str
    description: str
    version: str
    transform_fn: Callable


@dataclass
class ParserInfo:
    """Information about a registered parser."""

    name: str
    framework: str
    description: str
    version: str
    file_extensions: List[str]
    parser_class: Type


class TransformRegistry:
    """
    Registry for framework-to-framework transforms.

    Enables registration and discovery of transformation functions
    that convert between different page builder formats.
    """

    _instance: Optional["TransformRegistry"] = None
    _transforms: Dict[str, TransformInfo] = {}

    def __new__(cls) -> "TransformRegistry":
        """Singleton pattern for global registry."""
        if cls._instance is None:
            cls._instance = super().__new__(cls)
            cls._transforms = {}
        return cls._instance

    @classmethod
    def register(
        cls,
        name: str,
        source_framework: str,
        target_framework: str,
        description: str = "",
        version: str = "1.0.0",
    ) -> Callable:
        """
        Decorator to register a transform function.

        Args:
            name: Unique name for this transform
            source_framework: Source framework (e.g., "elementor")
            target_framework: Target framework (e.g., "bootstrap")
            description: Human-readable description
            version: Version of this transform

        Returns:
            Decorator function
        """
        def decorator(fn: Callable) -> Callable:
            key = f"{source_framework}:{target_framework}"
            cls._transforms[key] = TransformInfo(
                name=name,
                source_framework=source_framework,
                target_framework=target_framework,
                description=description or fn.__doc__ or "",
                version=version,
                transform_fn=fn,
            )
            return fn
        return decorator

    @classmethod
    def get_transform(cls, source: str, target: str) -> Optional[Callable]:
        """
        Get a transform function for the given source and target.

        Args:
            source: Source framework name
            target: Target framework name

        Returns:
            Transform function or None if not found
        """
        key = f"{source}:{target}"
        info = cls._transforms.get(key)
        return info.transform_fn if info else None

    @classmethod
    def list_transforms(cls) -> List[TransformInfo]:
        """List all registered transforms."""
        return list(cls._transforms.values())

    @classmethod
    def get_supported_pairs(cls) -> List[tuple]:
        """Get list of supported (source, target) pairs."""
        return [
            (info.source_framework, info.target_framework)
            for info in cls._transforms.values()
        ]

    @classmethod
    def clear(cls) -> None:
        """Clear all registered transforms (for testing)."""
        cls._transforms = {}


class ParserRegistry:
    """
    Registry for page builder format parsers.

    Enables registration and discovery of parser classes
    for different page builder file formats.
    """

    _instance: Optional["ParserRegistry"] = None
    _parsers: Dict[str, ParserInfo] = {}

    def __new__(cls) -> "ParserRegistry":
        """Singleton pattern for global registry."""
        if cls._instance is None:
            cls._instance = super().__new__(cls)
            cls._parsers = {}
        return cls._instance

    @classmethod
    def register(
        cls,
        name: str,
        framework: str,
        description: str = "",
        version: str = "1.0.0",
        file_extensions: Optional[List[str]] = None,
    ) -> Callable:
        """
        Decorator to register a parser class.

        Args:
            name: Unique name for this parser
            framework: Framework this parser handles
            description: Human-readable description
            version: Version of this parser
            file_extensions: List of supported file extensions

        Returns:
            Decorator function
        """
        def decorator(parser_cls: Type) -> Type:
            cls._parsers[framework] = ParserInfo(
                name=name,
                framework=framework,
                description=description or parser_cls.__doc__ or "",
                version=version,
                file_extensions=file_extensions or [".json"],
                parser_class=parser_cls,
            )
            return parser_cls
        return decorator

    @classmethod
    def get_parser(cls, framework: str) -> Optional[Type]:
        """
        Get a parser class for the given framework.

        Args:
            framework: Framework name

        Returns:
            Parser class or None if not found
        """
        info = cls._parsers.get(framework)
        return info.parser_class if info else None

    @classmethod
    def get_parser_for_extension(cls, extension: str) -> List[Type]:
        """
        Get parser classes that support the given file extension.

        Args:
            extension: File extension (e.g., ".json")

        Returns:
            List of parser classes
        """
        parsers = []
        for info in cls._parsers.values():
            if extension in info.file_extensions:
                parsers.append(info.parser_class)
        return parsers

    @classmethod
    def list_parsers(cls) -> List[ParserInfo]:
        """List all registered parsers."""
        return list(cls._parsers.values())

    @classmethod
    def get_supported_frameworks(cls) -> List[str]:
        """Get list of supported frameworks."""
        return list(cls._parsers.keys())

    @classmethod
    def clear(cls) -> None:
        """Clear all registered parsers (for testing)."""
        cls._parsers = {}


# Built-in transform registrations
@TransformRegistry.register(
    name="elementor_to_bootstrap",
    source_framework="elementor",
    target_framework="bootstrap",
    description="Transform Elementor JSON to Bootstrap HTML components",
    version="4.1.0",
)
def elementor_to_bootstrap(data: Any) -> str:
    """Transform Elementor JSON structure to Bootstrap 5 HTML."""
    from ..converters.bootstrap import BootstrapConverter
    converter = BootstrapConverter(include_metadata=True)
    return converter.convert(data)


@TransformRegistry.register(
    name="bootstrap_to_elementor",
    source_framework="bootstrap",
    target_framework="elementor",
    description="Transform Bootstrap HTML to Elementor JSON structure",
    version="4.1.0",
)
def bootstrap_to_elementor(data: Any) -> Any:
    """Transform Bootstrap HTML structure to Elementor JSON format."""
    # This would parse Bootstrap HTML and generate Elementor JSON
    # Placeholder for actual implementation
    return data
